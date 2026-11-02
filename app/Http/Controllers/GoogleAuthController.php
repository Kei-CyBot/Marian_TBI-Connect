<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\IntakeSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $this->ensureConfigured();

        return Socialite::driver('google')
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent select_account',
            ])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $this->ensureConfigured();

        if ($request->query('error')) {
            return to_route('login')->with('error', 'Google sign-in was cancelled or denied. Please try again.');
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (InvalidStateException $e) {
            Log::warning('Google OAuth state mismatch: '.$e->getMessage());

            return to_route('login')->with('error', 'Your Google sign-in session expired. Please start again.');
        } catch (\Throwable $e) {
            Log::error('Google OAuth Callback Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return to_route('login')->with('error', 'Google sign-in failed. Please try again.');
        }

        $googleId = (string) $googleUser->getId();
        $googleEmail = (string) $googleUser->getEmail();
        $email = strtolower(trim($googleEmail));

        if ($email === '') {
            return to_route('login')->with('error', 'Google did not share an email address. Please allow email access and try again.');
        }

        if ($request->user()) {
            User::query()
                ->where('google_id', $googleId)
                ->where('id', '!=', $request->user()->id)
                ->update(['google_id' => null]);

            $this->linkGoogleAccount($request->user(), $googleUser);
            $request->session()->regenerate();

            return to_route('dashboard')->with('status', 'Google Account Connected!');
        }

        $user = User::query()->where('google_id', $googleId)->first();

        if (! $user) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($user) {
                User::query()
                    ->where('google_id', $googleId)
                    ->where('id', '!=', $user->id)
                    ->update(['google_id' => null]);
            }
        }

        if ($user) {
            $this->linkGoogleAccount($user, $googleUser);
            $request->session()->forget('oauth_register');
            Auth::login($user);
            $request->session()->regenerate();

            return $this->redirectAfterGoogleLogin($request);
        }

        $pending = IntakeSubmission::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereRaw('LOWER(status) = ?', ['pending'])
            ->exists()
            || ContactRegistrationApplication::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereRaw('LOWER(status) = ?', ['pending'])
                ->exists();

        if ($pending) {
            $request->session()->forget('oauth_register');

            return to_route('public.apply-status')
                ->with('info', 'Your application for the Marian TBI Incubation Program is currently under admin review.');
        }

        $user = DB::transaction(function () use ($googleUser, $email, $googleId): User {
            $user = User::create([
                'name' => $this->googleName($googleUser, $email),
                'email' => $email,
                'password' => Str::random(32),
                'role' => User::ROLE_MEMBER_INCUBATEE,
                'google_id' => $googleId,
            ]);

            $this->linkGoogleAccount($user, $googleUser);

            Contact::query()->firstOrCreate(
                ['email' => $email],
                [
                    'side' => 'external',
                    'name' => $user->name,
                    'affiliation_type' => 'Other',
                    'role' => 'Member',
                    'domains' => [],
                    'technical_skills' => [],
                    'incubatees' => [],
                    'activity_log' => [],
                    'nda_status' => 'Pending',
                    'verification_status' => 'Pending Verification',
                ]
            );

            return $user;
        });

        $request->session()->forget('oauth_register');
        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectAfterGoogleLogin($request, 'Welcome to Marian TBI Connect! Your account was created with Google.');
    }

    private function redirectAfterGoogleLogin(Request $request, string $status = 'Signed in with Google.'): RedirectResponse
    {
        if ($request->session()->get('force_password_change')) {
            return to_route('profile.edit')->with('status', 'Please set your personal account password to complete your setup.');
        }

        return to_route('dashboard')->with('status', $status);
    }

    private function linkGoogleAccount(User $user, object $googleUser): void
    {
        $avatar = method_exists($googleUser, 'getAvatar') ? $googleUser->getAvatar() : null;

        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'google_email' => $googleUser->getEmail(),
            'avatar' => $avatar ?: $user->avatar,
            'google_access_token' => $googleUser->token,
            'google_refresh_token' => $googleUser->refreshToken ?? $user->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds((int) ($googleUser->expiresIn ?? 3600)),
        ])->save();
    }

    private function googleName(object $googleUser, string $email): string
    {
        $name = method_exists($googleUser, 'getName') ? trim((string) $googleUser->getName()) : '';

        return $name !== '' ? $name : $email;
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'google_id' => null,
            'google_email' => null,
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ])->save();

        return back()->with('success', 'Your Google Account has been unlinked. System operations will use central Marian TBI credentials.');
    }

    public function handleMagicLogin(Request $request, string $token): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return to_route('login')->with('error', 'This login link has expired or is invalid.');
        }

        $email = $request->query('email');
        if (! is_string($email) || $email === '') {
            return to_route('login')->with('error', 'This login link has expired or is invalid.');
        }

        $record = DB::table('one_time_login_tokens')->where('email', $email)->first();
        if (! $record || ! Hash::check($token, $record->token)) {
            return to_route('login')->with('error', 'Login token is invalid or already used.');
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            return to_route('login')->with('error', 'User account not found.');
        }

        DB::table('one_time_login_tokens')->where('email', $email)->delete();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('force_password_change', true);

        return redirect()->route('profile.edit')->with('status', 'Welcome to Marian TBI Connect! Please set your personal account password to complete your setup.');
    }

    private function ensureConfigured(): void
    {
        abort_unless(
            config('services.google.client_id') && config('services.google.client_secret'),
            503,
            'Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET before connecting a Google account.'
        );
    }
}
