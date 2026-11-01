<?php

namespace App\Console\Commands;

use App\Models\GoogleDriveConnection;
use App\Models\User;
use App\Support\GoogleDriveService;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Gmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CheckConnectivity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:connectivity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit and verify Database, Storage Symlink, OAuth Credentials, Gmail API, and Google Drive API connectivity.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $results = [];

        // Check 1: Database Connection
        try {
            DB::connection()->getPdo();
            $results[] = ['Service' => 'Database', 'Status' => '[OK]', 'Details' => 'Database Connected'];
        } catch (\Throwable $e) {
            $results[] = ['Service' => 'Database', 'Status' => '[FAIL]', 'Details' => $e->getMessage()];
        }

        // Check 2: Public Storage Symlink
        if (File::exists(public_path('storage'))) {
            $results[] = ['Service' => 'Public Storage Symlink', 'Status' => '[OK]', 'Details' => 'Public Storage Symlinked'];
        } else {
            $results[] = ['Service' => 'Public Storage Symlink', 'Status' => '[FAIL]', 'Details' => 'Storage link missing (Run php artisan storage:link)'];
        }

        // Check 3: Google Environment Variables
        $clientId = env('GOOGLE_CLIENT_ID');
        $clientSecret = env('GOOGLE_CLIENT_SECRET');
        $redirectUri = env('GOOGLE_REDIRECT_URI');
        if (! empty($clientId) && ! empty($clientSecret) && ! empty($redirectUri)) {
            $results[] = ['Service' => 'Google Environment Variables', 'Status' => '[OK]', 'Details' => 'All Google OAuth credentials configured'];
        } else {
            $missing = [];
            if (empty($clientId)) {
                $missing[] = 'GOOGLE_CLIENT_ID';
            }
            if (empty($clientSecret)) {
                $missing[] = 'GOOGLE_CLIENT_SECRET';
            }
            if (empty($redirectUri)) {
                $missing[] = 'GOOGLE_REDIRECT_URI';
            }
            $results[] = ['Service' => 'Google Environment Variables', 'Status' => '[FAIL]', 'Details' => 'Missing: ' . implode(', ', $missing)];
        }

        // Check 4: Saved OAuth Tokens
        $userWithToken = User::query()->whereNotNull('google_refresh_token')->first();
        if ($userWithToken) {
            $results[] = ['Service' => 'Saved OAuth Tokens', 'Status' => '[OK]', 'Details' => "User with token found: {$userWithToken->email}"];
        } else {
            $driveConnection = GoogleDriveConnection::query()->where('singleton_key', 'marian-tbi')->first();
            if ($driveConnection && ! empty($driveConnection->refresh_token)) {
                $results[] = ['Service' => 'Saved OAuth Tokens', 'Status' => '[OK]', 'Details' => "Google Drive connection found: {$driveConnection->google_email}"];
            } else {
                $results[] = ['Service' => 'Saved OAuth Tokens', 'Status' => '[FAIL]', 'Details' => 'No users with Google credentials found'];
            }
        }

        // Check 5: Live Gmail API Request
        try {
            $googleDriveService = app(GoogleDriveService::class);
            $connection = $googleDriveService->connection();
            if (! $connection) {
                throw new \Exception('No Google Drive connection available');
            }
            $accessToken = $googleDriveService->accessToken();
            $client = new Client();
            $client->setAccessToken($accessToken);
            $gmail = new Gmail($client);
            $profile = $gmail->users->getProfile('me');
            $email = $profile->getEmailAddress();
            $results[] = ['Service' => 'Gmail API', 'Status' => '[OK]', 'Details' => "Connected as {$email}"];
        } catch (\Throwable $e) {
            $results[] = ['Service' => 'Gmail API', 'Status' => '[FAIL]', 'Details' => $e->getMessage()];
        }

        // Check 6: Live Google Drive API Request
        try {
            $googleDriveService = app(GoogleDriveService::class);
            $connection = $googleDriveService->connection();
            if (! $connection) {
                throw new \Exception('No Google Drive connection available');
            }
            $accessToken = $googleDriveService->accessToken();
            $client = new Client();
            $client->setAccessToken($accessToken);
            $drive = new Drive($client);
            $files = $drive->files->listFiles(['pageSize' => 1]);
            $results[] = ['Service' => 'Google Drive API', 'Status' => '[OK]', 'Details' => 'Folder sync status OK (API accessible)'];
        } catch (\Throwable $e) {
            $results[] = ['Service' => 'Google Drive API', 'Status' => '[FAIL]', 'Details' => $e->getMessage()];
        }

        $this->table(['Service', 'Status', 'Details'], $results);

        return Command::SUCCESS;
    }
}
