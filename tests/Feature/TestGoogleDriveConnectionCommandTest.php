<?php

namespace Tests\Feature;

use App\Models\GoogleDriveConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TestGoogleDriveConnectionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_reports_a_reachable_folder(): void
    {
        $this->configureDrive('folder-123');
        Http::fake([
            'https://www.googleapis.com/drive/v3/files?q=*' => Http::response(['files' => []]),
            'https://www.googleapis.com/drive/v3/files/folder-123*' => Http::response([
                'id' => 'folder-123',
                'name' => 'Marian TBI Storage',
                'mimeType' => 'application/vnd.google-apps.folder',
                'trashed' => false,
            ]),
            'https://www.googleapis.com/upload/drive/v3/files*' => Http::response([
                'id' => 'test-file',
                'name' => 'drive-health-check.txt',
            ]),
            'https://www.googleapis.com/drive/v3/files/test-file*' => Http::response([], 204),
        ]);

        $this->artisan('drive:health-check')
            ->expectsOutputToContain('Access token resolved.')
            ->expectsOutputToContain('Folder is reachable.')
            ->expectsOutputToContain('Uploaded test file: test-file')
            ->expectsOutputToContain('Health check passed.')
            ->assertExitCode(0);
    }

    public function test_health_check_auto_creates_the_storage_folder(): void
    {
        $this->configureDrive(null);
        Http::fake([
            'https://www.googleapis.com/drive/v3/files?q=*' => Http::response(['files' => []]),
            'https://www.googleapis.com/drive/v3/files?fields=id,name,webViewLink*' => Http::response([
                'id' => 'new-folder',
                'name' => 'Marian TBI Storage',
            ]),
            'https://www.googleapis.com/drive/v3/files/new-folder*' => Http::response([
                'id' => 'new-folder',
                'name' => 'Marian TBI Storage',
                'mimeType' => 'application/vnd.google-apps.folder',
                'trashed' => false,
            ]),
            'https://www.googleapis.com/upload/drive/v3/files*' => Http::response([
                'id' => 'test-file',
                'name' => 'drive-health-check.txt',
            ]),
            'https://www.googleapis.com/drive/v3/files/test-file*' => Http::response([], 204),
        ]);

        $this->artisan('drive:health-check')
            ->expectsOutputToContain('Created Drive storage folder: new-folder')
            ->expectsOutputToContain('Deleted test file.')
            ->expectsOutputToContain('Health check passed.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('settings', [
            'key' => 'google.drive_folder_id',
            'value' => 'new-folder',
        ]);

        $this->assertSame('new-folder', GoogleDriveConnection::query()->first()->root_folder_id);
    }

    public function test_health_check_displays_the_raw_google_api_error(): void
    {
        $this->configureDrive('missing-folder');
        Http::fake([
            'https://www.googleapis.com/drive/v3/files/missing-folder*' => Http::response([
                'error' => ['message' => 'File not found: missing-folder.'],
            ], 404),
        ]);

        $this->artisan('drive:health-check')
            ->expectsOutputToContain('HTTP 404')
            ->expectsOutputToContain('File not found: missing-folder.')
            ->assertExitCode(1);
    }

    private function configureDrive(?string $folderId): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
            'services.google.refresh_token' => null,
            'services.google.folder_id' => $folderId,
        ]);

        GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => User::factory()->create()->id,
        ]);
    }
}
