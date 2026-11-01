<?php

namespace Tests\Feature;

use App\Models\FormSubmission;
use App\Models\GoogleDriveConnection;
use App\Models\Startup;
use App\Models\User;
use App\Support\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleDriveSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_private_folder_tree_and_uploads_form_response(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('form-attachments/application.pdf', 'sample application');
        $user = User::factory()->create(['email' => 'staff@example.test']);
        GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => $user->id,
        ]);
        $startup = Startup::query()->create([
            'name' => 'Test Incubatee',
            'founder' => 'Test Founder',
            'contact_email' => 'founder@example.test',
            'track' => 'internal',
            'status' => 'Incubatee',
            'cohort_id' => 1,
            'folder_access_manager_id' => $user->id,
        ]);
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 1,
            'form_name' => 'Startup profile',
            'startup_name' => $startup->name,
            'track' => $startup->track,
            'data' => ['business_name' => $startup->name],
            'prerequisite_path' => 'form-attachments/application.pdf',
            'prerequisite_original_name' => 'application.pdf',
            'prerequisite_mime_type' => 'application/pdf',
        ]);
        $createdFolderCount = 0;
        $uploadedFileCount = 0;
        Http::fake(function (ClientRequest $request) use (&$createdFolderCount, &$uploadedFileCount) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($request->method() === 'POST' && $path === '/drive/v3/files') {
                $createdFolderCount++;
                $id = $createdFolderCount === 1 ? 'marian-root' : 'incubatee-folder';

                return Http::response([
                    'id' => $id,
                    'name' => 'Folder',
                    'webViewLink' => 'https://drive.google.com/drive/folders/'.$id,
                ]);
            }
            if ($request->method() === 'GET' && str_contains($path, '/permissions')) {
                return Http::response(['permissions' => []]);
            }
            if ($request->method() === 'POST' && str_contains($path, '/permissions')) {
                return Http::response(['id' => 'drive-permission']);
            }
            if ($request->method() === 'POST' && $path === '/upload/drive/v3/files') {
                $uploadedFileCount++;

                return Http::response([
                    'id' => $uploadedFileCount === 1 ? 'response-file' : 'attachment-file',
                    'name' => $uploadedFileCount === 1 ? 'form-response.json' : 'application.pdf',
                    'webViewLink' => 'https://drive.google.com/file/d/'.$uploadedFileCount.'/view',
                ]);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        $result = app(GoogleDriveService::class)->syncAll();

        $this->assertSame(['folders' => 1, 'forms' => 1], $result);
        $this->assertSame(2, $uploadedFileCount);
        $this->assertSame('incubatee-folder', $startup->refresh()->google_drive_folder_id);
        $this->assertSame('response-file', $submission->refresh()->google_drive_response_file_id);
        $this->assertSame('attachment-file', $submission->google_drive_attachment_file_id);
        $this->assertDatabaseHas('startup_folder_drive_permissions', [
            'startup_id' => $startup->id,
            'user_id' => $user->id,
            'google_permission_id' => 'drive-permission',
        ]);
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/upload/drive/v3/files')
            && str_contains($request->body(), $startup->name));
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/upload/drive/v3/files')
            && str_contains($request->body(), 'application.pdf'));
    }

    public function test_connection_check_accepts_user_tokens_or_system_config(): void
    {
        $drive = app(GoogleDriveService::class);

        $this->assertFalse($drive->isConnected());

        $user = User::factory()->create();
        $this->assertFalse($drive->isConnected($user));

        $user->forceFill(['google_refresh_token' => 'user-refresh-token'])->save();
        $this->assertTrue($drive->isConnected($user));

        config(['services.google.refresh_token' => 'central-refresh-token']);
        $this->assertTrue($drive->isConnected());

        config(['services.google.refresh_token' => null, 'services.google.client_id' => 'client-id']);
        $this->assertTrue($drive->isConnected());
    }

    public function test_access_token_prefers_the_authenticated_user_token(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['google_access_token' => 'user-token'])->save();
        config(['services.google.refresh_token' => 'central-token']);

        Http::fake(['https://www.googleapis.com/drive/v3/*' => Http::response([], 200)]);

        $this->actingAs($user);
        app(GoogleDriveService::class)->moveFile('file-1', 'folder-1', 'old-folder');

        Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/files/file-1')
            && $request->hasHeader('Authorization', 'Bearer user-token'));
        Http::assertNotSent(fn (ClientRequest $request): bool => str_contains($request->url(), 'oauth2.googleapis.com/token'));
    }

    public function test_access_token_uses_the_system_refresh_token_when_the_user_has_none(): void
    {
        config([
            'services.google.refresh_token' => 'central-token',
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'system-token', 'expires_in' => 3600]),
            'https://www.googleapis.com/drive/v3/*' => Http::response([], 200),
        ]);

        $this->actingAs(User::factory()->create());
        app(GoogleDriveService::class)->moveFile('file-2', 'folder-2', 'old-folder');

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request->data()['refresh_token'] === 'central-token');
        Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/files/file-2')
            && $request->hasHeader('Authorization', 'Bearer system-token'));
    }

    public function test_access_token_requires_a_user_token_or_system_refresh_token(): void
    {
        $this->actingAs(User::factory()->create());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google Drive system credentials (CLIENT_ID, CLIENT_SECRET, REFRESH_TOKEN) are missing in .env or config.');

        app(GoogleDriveService::class)->moveFile('file-3', 'folder-3', 'old-folder');
    }

    public function test_expired_user_access_token_is_refreshed_before_calling_drive(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'google_access_token' => 'expired-token',
            'google_refresh_token' => 'user-refresh-token',
            'google_token_expires_at' => now()->subHour(),
        ])->save();
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
            'https://www.googleapis.com/drive/v3/*' => Http::response([], 200),
        ]);

        $this->actingAs($user);
        app(GoogleDriveService::class)->moveFile('file-4', 'folder-4', 'old-folder');

        Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/files/file-4')
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
        $this->assertSame('fresh-token', $user->refresh()->google_access_token);
    }

    public function test_revoked_user_token_falls_back_to_system_credentials(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'google_access_token' => 'expired-token',
            'google_refresh_token' => 'revoked-token',
            'google_token_expires_at' => now()->subHour(),
        ])->save();
        config([
            'services.google.refresh_token' => 'central-token',
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
        ]);

        Log::spy();

        Http::fake([
            'https://oauth2.googleapis.com/token' => fn (ClientRequest $request) => $request->data()['refresh_token'] === 'revoked-token'
                ? Http::response(['error' => 'invalid_grant'], 400)
                : Http::response(['access_token' => 'system-token', 'expires_in' => 3600]),
            'https://www.googleapis.com/drive/v3/*' => Http::response([], 200),
        ]);

        $this->actingAs($user);
        app(GoogleDriveService::class)->moveFile('file-6', 'folder-6', 'old-folder');

        Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/files/file-6')
            && $request->hasHeader('Authorization', 'Bearer system-token'));
        $this->assertNull($user->fresh()->google_access_token);
        $this->assertNull($user->fresh()->google_refresh_token);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_401_forgets_the_cached_system_token_and_retries(): void
    {
        config([
            'services.google.refresh_token' => 'central-token',
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'system-token', 'expires_in' => 3600]),
            'https://www.googleapis.com/drive/v3/*' => Http::sequence()->pushStatus(401)->push([], 200),
        ]);

        $this->actingAs(User::factory()->create());
        app(GoogleDriveService::class)->moveFile('file-5', 'folder-5', 'old-folder');

        Http::assertSentCount(4);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://oauth2.googleapis.com/token');
    }
}
