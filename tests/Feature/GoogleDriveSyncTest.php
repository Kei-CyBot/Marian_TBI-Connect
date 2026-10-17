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
}
