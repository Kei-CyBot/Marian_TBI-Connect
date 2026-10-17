<?php

namespace App\Http\Controllers;

use App\Models\GoogleDriveConnection;
use App\Support\GoogleDriveService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\GoogleProvider;
use RuntimeException;

class GoogleDriveController extends Controller
{
    public function connect(SocialiteManager $socialite): RedirectResponse
    {
        abort_unless(
            config('services.google.client_id') && config('services.google.client_secret'),
            503,
            'Configure GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET before connecting Google Drive.'
        );
        abort_unless(
            config('services.google.drive_account_email'),
            503,
            'Set GOOGLE_DRIVE_ACCOUNT_EMAIL to the dedicated Marian TBI Google account before connecting Drive.'
        );

        return $this->driveProvider($socialite)
            ->scopes(['https://www.googleapis.com/auth/drive.file'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request, SocialiteManager $socialite): RedirectResponse
    {
        abort_unless(
            config('services.google.client_id')
                && config('services.google.client_secret')
                && config('services.google.drive_account_email'),
            503,
            'Configure the Google OAuth client and dedicated Marian TBI account before connecting Drive.'
        );

        $googleUser = $this->driveProvider($socialite)->user();
        $email = $googleUser->getEmail();
        $isVerified = filter_var($googleUser->user['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
        abort_unless($email && $isVerified, 403, 'Google could not verify this Drive account.');
        abort_unless(
            strcasecmp($email, config('services.google.drive_account_email')) === 0,
            403,
            'Connect using the configured dedicated Marian TBI Google account.'
        );

        $connection = GoogleDriveConnection::query()->firstOrNew(['singleton_key' => 'marian-tbi']);
        abort_if(
            $connection->exists && strcasecmp($connection->google_email, $email) !== 0,
            409,
            'A different dedicated Google Drive account is already connected.'
        );

        $refreshToken = $googleUser->refreshToken ?? $connection->refresh_token;
        abort_unless(
            is_string($refreshToken) && $refreshToken !== '',
            422,
            'Google did not return a refresh token. Revoke Marian TBI Connect in Google Account permissions and reconnect.'
        );

        $connection->fill([
            'google_email' => $email,
            'access_token' => $googleUser->token,
            'refresh_token' => $refreshToken,
            'expires_at' => now()->addSeconds((int) ($googleUser->expiresIn ?? 3600)),
            'connected_by' => $request->user()->id,
        ])->save();

        return to_route('files')->with('status', 'Google Drive connected as '.$email.'. Use Sync to create incubatee folders and upload existing form records.');
    }

    private function driveProvider(SocialiteManager $socialite): AbstractProvider
    {
        $config = config('services.google');
        $config['redirect'] = $config['drive_redirect'];

        return $socialite->buildProvider(GoogleProvider::class, $config);
    }

    public function synchronize(GoogleDriveService $drive): RedirectResponse
    {
        abort_unless($drive->isConnected(), 409, 'Connect the dedicated Marian TBI Google account before syncing files.');

        try {
            $result = $drive->syncAll();
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error('Google Drive sync failed.', ['exception' => $exception]);

            return to_route('files')->with('drive_sync_warning', 'Google Drive sync failed. Local form records were kept; completed uploads can be retried. Check the application log and Google Drive connection.');
        }

        return to_route('files')->with(
            'status',
            'Google Drive sync completed: '.$result['folders'].' incubatee folders and '.$result['forms'].' form records processed.'
        );
    }
}
