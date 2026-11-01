<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $this->ensureConfigured();

        return Socialite::driver('google')
            ->stateless()
            ->scopes([
                'https://www.googleapis.com/auth/drive.file',
                'https://www.googleapis.com/auth/gmail.send',
                'https://www.googleapis.com/auth/gmail.readonly',
            ])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent select_account',
            ])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->ensureConfigured();

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $user = $request->user();

            if (! $user) {
                throw new \Exception('No authenticated user found.');
            }

            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'google_access_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken ?? $user->google_refresh_token,
                'google_token_expires_at' => now()->addSeconds((int) ($googleUser->expiresIn ?? 3600)),
            ])->save();

            return to_route('dashboard')->with('status', 'Google Account Connected!');
        } catch (\Throwable $e) {
            Log::error('Google OAuth Callback Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect('/dashboard')->with('error', 'Google Auth Failed: ' . $e->getMessage());
        }
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
