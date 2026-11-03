<?php

namespace App\Console\Commands;

use App\Models\GoogleDriveConnection;
use App\Models\Setting;
use App\Models\User;
use App\Support\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TestGoogleDriveConnection extends Command
{
    protected $signature = 'drive:health-check';

    protected $description = 'Check Google Drive credentials, target folder, and API connectivity.';

    public function handle(GoogleDriveService $drive): int
    {
        $this->info('=== Google Drive Health Check ===');

        $connection = $drive->connection();
        $settingFolderId = Setting::query()->where('key', 'google.drive_folder_id')->value('value');
        $envFolderId = config('services.google.folder_id');
        $connectionFolderId = $connection?->root_folder_id;
        $folderId = $drive->configuredRootFolderId() ?: $connectionFolderId;

        $this->line('Credentials:');
        $this->line('  GOOGLE_DRIVE_CLIENT_ID: '.$this->presence(config('services.google.client_id')));
        $this->line('  GOOGLE_DRIVE_CLIENT_SECRET: '.$this->presence(config('services.google.client_secret')));

        $this->line('OAuth tokens:');
        $this->line('  GOOGLE_DRIVE_REFRESH_TOKEN (.env): '.$this->presence(config('services.google.refresh_token')));
        $this->line('  DB connection: '.($connection?->google_email ?: 'none'));
        $this->line('  DB access token: '.$this->presence($connection?->access_token));
        $this->line('  DB refresh token: '.$this->presence($connection?->refresh_token));
        $this->line('  Users with Google tokens: '.User::query()
            ->where(fn ($query) => $query->whereNotNull('google_access_token')->orWhereNotNull('google_refresh_token'))
            ->count());

        $this->line('Target folder:');
        $this->line('  DB setting google.drive_folder_id: '.($settingFolderId ?: 'missing'));
        $this->line('  .env GOOGLE_DRIVE_FOLDER_ID: '.($envFolderId ?: 'missing'));
        $this->line('  DB connection root folder ID: '.($connectionFolderId ?: 'missing'));
        $this->line('  Using folder ID: '.($folderId ?: 'missing'));

        $missing = [];
        if (! filled(config('services.google.client_id'))) {
            $missing[] = 'GOOGLE_DRIVE_CLIENT_ID';
        }
        if (! filled(config('services.google.client_secret'))) {
            $missing[] = 'GOOGLE_DRIVE_CLIENT_SECRET';
        }
        if (! $this->hasAnyToken($connection)) {
            $missing[] = 'OAuth token (env refresh token, DB connection, or user token)';
        }

        if ($missing !== []) {
            $this->newLine();
            $this->error('Missing configuration: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Resolving access token...');
        try {
            $token = $drive->accessToken();
            $this->info('  [OK] Access token resolved.');
        } catch (\Throwable $exception) {
            $this->error('  [FAIL] '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! $folderId) {
            $this->line('No target folder configured; creating "Marian TBI Storage"...');
            try {
                $folderId = $drive->getOrCreateTargetFolderId();
                $this->info('  [OK] Created Drive storage folder: '.$folderId);
            } catch (RequestException $exception) {
                $this->error('  [FAIL] Google API returned HTTP '.$exception->response->status().' '.$exception->response->reason());
                $this->line('  Raw response: '.$exception->response->body());

                return self::FAILURE;
            } catch (\Throwable $exception) {
                $this->error('  [FAIL] '.$exception->getMessage());

                return self::FAILURE;
            }
        } else {
            $this->line('Using configured folder ID: '.$folderId);
        }

        $this->line('Pinging Google Drive folder '.$folderId.'...');
        try {
            $response = Http::withToken($token)
                ->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($folderId), [
                    'fields' => 'id,name,mimeType,trashed',
                    'supportsAllDrives' => 'true',
                ])
                ->throw();

            $this->info('  [OK] Folder is reachable.');
            $this->line('  Name: '.($response->json('name') ?: 'unknown'));
            $this->line('  ID: '.($response->json('id') ?: $folderId));
            $this->line('  MIME type: '.($response->json('mimeType') ?: 'unknown'));
            $this->line('  Trashed: '.($response->json('trashed') ? 'yes' : 'no'));
        } catch (RequestException $exception) {
            $this->error('  [FAIL] Google API returned HTTP '.$exception->response->status().' '.$exception->response->reason());
            $this->line('  Raw response: '.$exception->response->body());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error('  [FAIL] '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Uploading 1-byte test file...');
        try {
            $testFile = $drive->uploadFile('drive-health-check.txt', 'text/plain', 'x', $folderId);
            $this->info('  [OK] Uploaded test file: '.$testFile['id']);

            $drive->deleteFile($testFile['id']);
            $this->info('  [OK] Deleted test file.');
        } catch (RequestException $exception) {
            $this->error('  [FAIL] Google API returned HTTP '.$exception->response->status().' '.$exception->response->reason());
            $this->line('  Raw response: '.$exception->response->body());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error('  [FAIL] '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Health check passed.');

        return self::SUCCESS;
    }

    private function hasAnyToken(?GoogleDriveConnection $connection): bool
    {
        return filled(config('services.google.refresh_token'))
            || filled($connection?->access_token)
            || filled($connection?->refresh_token)
            || User::query()
                ->where(fn ($query) => $query->whereNotNull('google_access_token')->orWhereNotNull('google_refresh_token'))
                ->exists();
    }

    private function presence(mixed $value): string
    {
        return filled($value) ? 'set' : 'missing';
    }
}
