<?php

namespace Tests\Feature;

use App\Models\FormSubmission;
use App\Models\GoogleDriveConnection;
use App\Models\Startup;
use App\Models\User;
use App\Services\DocumentConversionService;
use App\Support\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_payload_is_converted_to_a_formatted_pdf(): void
    {
        $data = [
            'founder_name' => 'Maria Santos',
            'business_name' => 'AgriSense Labs',
            'team' => [
                ['name' => 'Ana Reyes', 'role' => 'CTO'],
            ],
        ];

        $service = app(DocumentConversionService::class);
        $html = $service->toHtml($data, [
            'title' => 'Startup Profile',
            'author' => 'Maria Santos',
            'startup' => 'AgriSense Labs',
        ]);
        $pdf = $service->toPdf($data, [
            'title' => 'Startup Profile',
            'author' => 'Maria Santos',
            'startup' => 'AgriSense Labs',
        ]);

        $this->assertStringContainsString('Startup Profile', $html);
        $this->assertStringContainsString('Maria Santos', $html);
        $this->assertStringContainsString('Ana Reyes', $html);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_deleting_a_file_removes_local_storage_drive_copies_and_database_record(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('form-prerequisites/agreement.pdf', 'pdf-bytes');
        Storage::disk('public')->put('form-submissions/agreement.pdf', 'pdf-bytes');

        $user = User::factory()->create(['email' => 'staff@example.test']);
        GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => $user->id,
        ]);
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 4,
            'form_name' => 'Signed agreement',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
            'prerequisite_path' => 'form-prerequisites/agreement.pdf',
            'prerequisite_original_name' => 'signed-agreement.pdf',
            'prerequisite_mime_type' => 'application/pdf',
            'local_path' => 'form-prerequisites/agreement.pdf',
            'google_drive_response_file_id' => 'drive-response',
            'google_drive_attachment_file_id' => 'drive-attachment',
            'google_drive_file_id' => 'drive-legacy',
        ]);

        $deletedUrls = [];
        Http::fake(function (ClientRequest $request) use (&$deletedUrls) {
            if ($request->method() === 'DELETE') {
                $deletedUrls[] = $request->url();

                return Http::response(null, 204);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method()], 500);
        });

        $this->actingAs($user)
            ->delete(route('form-submissions.destroy', $submission))
            ->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertSessionHas('status', 'Form submission deleted successfully.');

        $this->assertDatabaseMissing('form_submissions', ['id' => $submission->id]);
        Storage::disk('local')->assertMissing('form-prerequisites/agreement.pdf');
        Storage::disk('public')->assertMissing('form-submissions/agreement.pdf');
        $this->assertCount(3, $deletedUrls);
        $this->assertStringContainsString('/files/drive-response', $deletedUrls[0]);
        $this->assertStringContainsString('/files/drive-attachment', $deletedUrls[1]);
        $this->assertStringContainsString('/files/drive-legacy', $deletedUrls[2]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'file_deleted',
            'description' => "File 'signed-agreement.pdf' removed from local storage and Google Drive.",
        ]);
    }

    public function test_updating_a_synced_record_overwrites_the_existing_drive_file(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create(['email' => 'staff@example.test']);
        GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => $user->id,
        ]);
        Startup::query()->where('name', 'AgriSense Labs')->firstOrFail()->forceFill([
            'google_drive_folder_id' => 'agrisense-folder',
            'google_drive_folder_url' => 'https://drive.google.com/drive/folders/agrisense-folder',
        ])->save();
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
            'google_drive_response_file_id' => 'existing-response',
        ]);

        $patchedUrls = [];
        Http::fake(function (ClientRequest $request) use (&$patchedUrls) {
            if ($request->method() === 'GET' && parse_url($request->url(), PHP_URL_PATH) === '/drive/v3/files/existing-response') {
                return Http::response(['id' => 'existing-response', 'name' => 'form-response.html', 'trashed' => false]);
            }
            if ($request->method() === 'PATCH' && str_contains($request->url(), '/upload/drive/v3/files/existing-response')) {
                $patchedUrls[] = $request->url();

                return Http::response([
                    'id' => 'existing-response',
                    'webViewLink' => 'https://drive.example/existing-response',
                ]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/permissions')) {
                return Http::response([
                    'permissions' => [
                        ['id' => 'permission-1', 'type' => 'user', 'role' => 'reader', 'emailAddress' => 'staff@example.test'],
                    ],
                ]);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        $this->actingAs($user)->put(route('form-submissions.update', $submission), [
            'form_id' => 2,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => ['founder' => 'Maria Santos (updated)'],
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));

        $this->assertCount(1, $patchedUrls);
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'PATCH'
            && str_contains($request->url(), '/upload/drive/v3/files/existing-response'));
        Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/upload/drive/v3/files'));

        $submission->refresh();
        $this->assertSame('existing-response', $submission->google_drive_response_file_id);
        $this->assertSame('https://drive.example/existing-response', $submission->google_drive_response_url);
        $this->assertSame('Maria Santos (updated)', $submission->data['founder']);
    }

    public function test_uploading_a_file_without_a_drive_id_adopts_an_existing_file_with_the_same_name(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create(['email' => 'staff@example.test']);
        GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => $user->id,
        ]);
        Startup::query()->where('name', 'AgriSense Labs')->firstOrFail()->forceFill([
            'google_drive_folder_id' => 'agrisense-folder',
            'google_drive_folder_url' => 'https://drive.google.com/drive/folders/agrisense-folder',
        ])->save();
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
        ]);

        $searchedUrls = [];
        $patchedUrls = [];
        Http::fake(function (ClientRequest $request) use ($submission, &$searchedUrls, &$patchedUrls) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($request->method() === 'GET' && $path === '/drive/v3/files') {
                $searchedUrls[] = urldecode($request->url());

                return Http::response([
                    'files' => [[
                        'id' => 'adopted-response',
                        'name' => 'form-'.$submission->getKey().'-response.html',
                        'webViewLink' => 'https://docs.google.com/document/d/adopted-response/edit',
                    ]],
                ]);
            }
            if ($request->method() === 'PATCH' && str_contains($request->url(), '/upload/drive/v3/files/adopted-response')) {
                $patchedUrls[] = $request->url();

                return Http::response([
                    'id' => 'adopted-response',
                    'webViewLink' => 'https://docs.google.com/document/d/adopted-response/edit',
                ]);
            }
            if ($request->method() === 'GET' && str_contains($path, '/permissions')) {
                return Http::response([
                    'permissions' => [
                        ['id' => 'permission-1', 'type' => 'user', 'role' => 'reader', 'emailAddress' => 'staff@example.test'],
                    ],
                ]);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        $this->actingAs($user)->put(route('form-submissions.update', $submission), [
            'form_id' => 2,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => ['founder' => 'Maria Santos (updated)'],
        ])
            ->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertSessionMissing('drive_sync_warning');

        $this->assertCount(1, $patchedUrls);
        $this->assertStringContainsString(
            "name = 'form-".$submission->getKey()."-response.html'",
            $searchedUrls[0] ?? ''
        );
        $this->assertStringContainsString('trashed = false', $searchedUrls[0] ?? '');
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'PATCH'
            && str_contains($request->url(), '/upload/drive/v3/files/adopted-response')
            && str_contains($request->body(), 'google-apps.document'));
        Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/upload/drive/v3/files'));

        $submission->refresh();
        $this->assertSame('adopted-response', $submission->google_drive_response_file_id);
        $this->assertSame('https://docs.google.com/document/d/adopted-response/edit', $submission->google_drive_response_url);
    }

    public function test_updating_a_record_whose_drive_file_was_deleted_reuploads_it(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create(['email' => 'staff@example.test']);
        GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => $user->id,
        ]);
        Startup::query()->where('name', 'AgriSense Labs')->firstOrFail()->forceFill([
            'google_drive_folder_id' => 'agrisense-folder',
            'google_drive_folder_url' => 'https://drive.google.com/drive/folders/agrisense-folder',
        ])->save();
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
            'google_drive_file_id' => 'legacy-response',
            'google_drive_response_file_id' => 'deleted-response',
            'google_drive_response_url' => 'https://drive.example/deleted-response',
        ]);

        $patched = 0;
        $created = 0;
        Http::fake(function (ClientRequest $request) use (&$patched, &$created) {
            if ($request->method() === 'GET' && parse_url($request->url(), PHP_URL_PATH) === '/drive/v3/files/deleted-response') {
                return Http::response(['error' => ['code' => 404, 'message' => 'File not found']], 404);
            }
            if ($request->method() === 'PATCH' && str_contains($request->url(), '/upload/drive/v3/files/deleted-response')) {
                $patched++;

                return Http::response(['error' => ['code' => 404, 'message' => 'File not found']], 404);
            }
            if ($request->method() === 'GET' && parse_url($request->url(), PHP_URL_PATH) === '/drive/v3/files') {
                return Http::response(['files' => []]);
            }
            if ($request->method() === 'POST' && str_contains($request->url(), '/upload/drive/v3/files')) {
                $created++;

                return Http::response([
                    'id' => 'replacement-response',
                    'webViewLink' => 'https://docs.google.com/document/d/replacement-response/edit',
                ]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/permissions')) {
                return Http::response([
                    'permissions' => [
                        ['id' => 'permission-1', 'type' => 'user', 'role' => 'reader', 'emailAddress' => 'staff@example.test'],
                    ],
                ]);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        $this->actingAs($user)->put(route('form-submissions.update', $submission), [
            'form_id' => 2,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => ['founder' => 'Maria Santos (updated)'],
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));

        $this->assertSame(0, $patched);
        $this->assertSame(1, $created);
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/upload/drive/v3/files')
            && str_contains($request->body(), 'form-'.$submission->getKey().'-response.html')
            && str_contains($request->body(), 'google-apps.document')
            && str_contains($request->body(), '"parents":["agrisense-folder"]'));

        $submission->refresh();
        $this->assertSame('replacement-response', $submission->google_drive_response_file_id);
        $this->assertSame('https://docs.google.com/document/d/replacement-response/edit', $submission->google_drive_response_url);
        $this->assertNull($submission->google_drive_file_id);
    }

    public function test_updating_a_file_that_is_trashed_on_drive_uploads_a_fresh_copy(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create(['email' => 'staff@example.test']);
        GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => $user->id,
        ]);
        Startup::query()->where('name', 'AgriSense Labs')->firstOrFail()->forceFill([
            'google_drive_folder_id' => 'agrisense-folder',
            'google_drive_folder_url' => 'https://drive.google.com/drive/folders/agrisense-folder',
        ])->save();
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
            'google_drive_file_id' => 'trashed-response',
            'google_drive_response_file_id' => 'trashed-response',
            'google_drive_response_url' => 'https://drive.example/trashed-response',
        ]);

        $responseIdDuringUpload = 'not-called';
        $legacyIdDuringUpload = 'not-called';
        $patched = 0;
        Http::fake(function (ClientRequest $request) use ($submission, &$responseIdDuringUpload, &$legacyIdDuringUpload, &$patched) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($request->method() === 'GET' && $path === '/drive/v3/files/trashed-response') {
                return Http::response(['id' => 'trashed-response', 'name' => 'form-response.html', 'trashed' => true]);
            }
            if ($request->method() === 'GET' && $path === '/drive/v3/files') {
                return Http::response(['files' => []]);
            }
            if ($request->method() === 'PATCH' && str_contains($request->url(), '/upload/drive/v3/files')) {
                $patched++;

                return Http::response(['error' => 'Unexpected update of a trashed file'], 500);
            }
            if ($request->method() === 'POST' && str_contains($request->url(), '/upload/drive/v3/files')) {
                $current = $submission->fresh();
                $responseIdDuringUpload = $current->google_drive_response_file_id;
                $legacyIdDuringUpload = $current->google_drive_file_id;

                return Http::response([
                    'id' => 'fresh-response',
                    'webViewLink' => 'https://docs.google.com/document/d/fresh-response/edit',
                ]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/permissions')) {
                return Http::response([
                    'permissions' => [
                        ['id' => 'permission-1', 'type' => 'user', 'role' => 'reader', 'emailAddress' => 'staff@example.test'],
                    ],
                ]);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        $this->actingAs($user)->put(route('form-submissions.update', $submission), [
            'form_id' => 2,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => ['founder' => 'Maria Santos (updated)'],
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertSessionHas('status', 'Form response updated for AgriSense Labs.');

        $this->assertSame(0, $patched);
        $this->assertNull($responseIdDuringUpload);
        $this->assertNull($legacyIdDuringUpload);
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET'
            && parse_url($request->url(), PHP_URL_PATH) === '/drive/v3/files/trashed-response');
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/upload/drive/v3/files')
            && str_contains($request->body(), '"parents":["agrisense-folder"]'));

        $submission->refresh();
        $this->assertSame('fresh-response', $submission->google_drive_response_file_id);
        $this->assertSame('https://docs.google.com/document/d/fresh-response/edit', $submission->google_drive_response_url);
        $this->assertNull($submission->google_drive_file_id);
    }

    public function test_saving_a_form_forces_a_media_write_to_drive(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create(['email' => 'staff@example.test']);
        GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => $user->id,
        ]);
        Startup::query()->where('name', 'AgriSense Labs')->firstOrFail()->forceFill([
            'google_drive_folder_id' => 'agrisense-folder',
            'google_drive_folder_url' => 'https://drive.google.com/drive/folders/agrisense-folder',
        ])->save();
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
            'google_drive_response_file_id' => 'existing-response',
        ]);

        $patchedBody = null;
        Http::fake(function (ClientRequest $request) use (&$patchedBody) {
            if ($request->method() === 'GET' && parse_url($request->url(), PHP_URL_PATH) === '/drive/v3/files/existing-response') {
                return Http::response(['id' => 'existing-response', 'name' => 'form-response.html', 'trashed' => false]);
            }
            if ($request->method() === 'PATCH' && str_contains($request->url(), '/upload/drive/v3/files/existing-response')) {
                $patchedBody = $request->body();

                return Http::response([
                    'id' => 'existing-response',
                    'webViewLink' => 'https://drive.example/existing-response',
                ]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/permissions')) {
                return Http::response([
                    'permissions' => [
                        ['id' => 'permission-1', 'type' => 'user', 'role' => 'reader', 'emailAddress' => 'staff@example.test'],
                    ],
                ]);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        $this->actingAs($user)->put(route('form-submissions.update', $submission), [
            'form_id' => 2,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => ['founder' => 'Maria Santos (updated)'],
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertSessionHas('status', 'Form response updated for AgriSense Labs.');

        $this->assertNotNull($patchedBody);
        $this->assertStringContainsString('form-'.$submission->getKey().'-response.html', $patchedBody);
        $this->assertStringContainsString('Maria Santos (updated)', $patchedBody);
    }

    public function test_google_drive_500_after_retries_keeps_the_local_update_and_warns(): void
    {
        Sleep::fake();
        Startup::ensureConfigured();
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('form-prerequisites/old-agreement.pdf', 'old-pdf-bytes');

        $user = User::factory()->create(['email' => 'staff@example.test']);
        GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => $user->id,
        ]);
        Startup::query()->where('name', 'AgriSense Labs')->firstOrFail()->forceFill([
            'google_drive_folder_id' => 'agrisense-folder',
            'google_drive_folder_url' => 'https://drive.google.com/drive/folders/agrisense-folder',
        ])->save();
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 4,
            'form_name' => 'Signed agreement',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
            'prerequisite_path' => 'form-prerequisites/old-agreement.pdf',
            'prerequisite_original_name' => 'old-agreement.pdf',
            'prerequisite_mime_type' => 'application/pdf',
            'local_path' => 'form-prerequisites/old-agreement.pdf',
            'google_drive_response_file_id' => 'existing-response',
        ]);

        $failedAttachmentAttempts = 0;
        Http::fake(function (ClientRequest $request) use (&$failedAttachmentAttempts) {
            if ($request->method() === 'GET' && parse_url($request->url(), PHP_URL_PATH) === '/drive/v3/files/existing-response') {
                return Http::response(['id' => 'existing-response', 'name' => 'form-response.html', 'trashed' => false]);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/permissions')) {
                return Http::response([
                    'permissions' => [
                        ['id' => 'permission-1', 'type' => 'user', 'role' => 'reader', 'emailAddress' => 'staff@example.test'],
                    ],
                ]);
            }
            if ($request->method() === 'PATCH' && str_contains($request->url(), '/upload/drive/v3/files/existing-response')) {
                return Http::response([
                    'id' => 'existing-response',
                    'webViewLink' => 'https://drive.example/existing-response',
                ]);
            }
            if ($request->method() === 'POST' && str_contains($request->url(), '/upload/drive/v3/files')) {
                $failedAttachmentAttempts++;

                return Http::response(['error' => 'Internal Server Error'], 500);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        $this->actingAs($user)->put(route('form-submissions.update', $submission), [
            'form_id' => 4,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => ['founder' => 'Maria Santos (updated)'],
            'prerequisite' => UploadedFile::fake()->create('replacement-agreement.pdf', 120, 'application/pdf'),
        ])
            ->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertSessionHas('status', 'Form response updated for AgriSense Labs.')
            ->assertSessionHas('drive_sync_warning', 'File saved locally. Cloud backup will retry shortly.');

        $this->assertSame(4, $failedAttachmentAttempts);

        $submission->refresh();
        $this->assertSame('Maria Santos (updated)', $submission->data['founder']);
        $this->assertSame('replacement-agreement.pdf', $submission->prerequisite_original_name);
        $this->assertNotSame('form-prerequisites/old-agreement.pdf', $submission->prerequisite_path);
        Storage::disk('local')->assertExists($submission->prerequisite_path);
        Storage::disk('public')->assertExists('form-submissions/'.basename($submission->prerequisite_path));
    }

    public function test_extract_folder_id_accepts_raw_ids_and_drive_urls(): void
    {
        $id = '1A2B3C4D5E6F7G8H9I0J1K2L3M4N5O6P';

        $this->assertSame($id, GoogleDriveService::extractFolderId($id));
        $this->assertSame($id, GoogleDriveService::extractFolderId('https://drive.google.com/drive/folders/'.$id));
        $this->assertSame($id, GoogleDriveService::extractFolderId('https://drive.google.com/drive/u/0/folders/'.$id));
        $this->assertNull(GoogleDriveService::extractFolderId('https://example.test/not-a-folder'));
        $this->assertNull(GoogleDriveService::extractFolderId(''));
        $this->assertNull(GoogleDriveService::extractFolderId(null));
    }

    public function test_upload_for_startup_with_custom_drive_url_targets_that_folder(): void
    {
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
            'name' => 'AgriSense Labs',
            'founder' => 'Maria Santos',
            'contact_email' => 'maria@example.test',
            'track' => 'internal',
            'status' => 'Incubatee',
            'cohort_id' => 1,
            'google_drive_folder_url' => 'https://drive.google.com/drive/folders/1TestFolderId123',
        ]);
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => $startup->name,
            'track' => $startup->track,
            'data' => ['business_name' => $startup->name],
        ]);

        Http::fake(function (ClientRequest $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($request->method() === 'GET' && $path === '/drive/v3/files') {
                return Http::response(['files' => []]);
            }
            if ($request->method() === 'POST' && $path === '/upload/drive/v3/files') {
                return Http::response([
                    'id' => 'uploaded-doc',
                    'webViewLink' => 'https://docs.google.com/document/d/uploaded-doc/edit',
                ]);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        app(GoogleDriveService::class)->syncOrUpdateFile($submission);

        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/upload/drive/v3/files')
            && str_contains($request->body(), '"parents":["1TestFolderId123"]'));
    }

    public function test_upload_for_startup_without_custom_folder_uses_marian_tbi_storage(): void
    {
        config(['services.google.folder_id' => null]);
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
            'name' => 'AgriSense Labs',
            'founder' => 'Maria Santos',
            'contact_email' => 'maria@example.test',
            'track' => 'internal',
            'status' => 'Incubatee',
            'cohort_id' => 1,
        ]);
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => $startup->name,
            'track' => $startup->track,
            'data' => ['business_name' => $startup->name],
        ]);

        Http::fake(function (ClientRequest $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($request->method() === 'GET' && $path === '/drive/v3/files') {
                return Http::response(['files' => []]);
            }
            if ($request->method() === 'POST' && $path === '/drive/v3/files') {
                return Http::response([
                    'id' => 'marian-storage',
                    'name' => 'Marian TBI Storage',
                    'webViewLink' => 'https://drive.google.com/drive/folders/marian-storage',
                ]);
            }
            if ($request->method() === 'POST' && $path === '/upload/drive/v3/files') {
                return Http::response([
                    'id' => 'uploaded-doc',
                    'webViewLink' => 'https://docs.google.com/document/d/uploaded-doc/edit',
                ]);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        app(GoogleDriveService::class)->syncOrUpdateFile($submission);

        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && parse_url($request->url(), PHP_URL_PATH) === '/drive/v3/files'
            && str_contains($request->body(), 'Marian TBI Storage'));
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/upload/drive/v3/files')
            && str_contains($request->body(), '"parents":["marian-storage"]'));
    }

    public function test_failed_google_document_conversion_retries_as_a_raw_html_file(): void
    {
        Sleep::fake();
        $user = User::factory()->create(['email' => 'staff@example.test']);
        GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => $user->id,
        ]);

        $conversionAttempts = 0;
        $fallbackAttempts = 0;
        Http::fake(function (ClientRequest $request) use (&$conversionAttempts, &$fallbackAttempts) {
            if (str_contains($request->url(), '/upload/drive/v3/files')) {
                if (str_contains($request->body(), 'google-apps.document')) {
                    $conversionAttempts++;

                    return Http::response(['error' => 'Internal Server Error'], 500);
                }

                $fallbackAttempts++;

                return Http::response([
                    'id' => 'raw-html-file',
                    'webViewLink' => 'https://drive.example/raw-html-file',
                ]);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        $result = app(GoogleDriveService::class)->uploadFile(
            'form.html',
            'text/html',
            '<html><body>Startup profile</body></html>',
            'folder-id',
            null,
            true
        );

        $this->assertSame('raw-html-file', $result['id']);
        $this->assertSame(4, $conversionAttempts);
        $this->assertSame(1, $fallbackAttempts);
        Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/upload/drive/v3/files')
            && str_contains($request->body(), 'form.html')
            && str_contains($request->body(), 'text/html')
            && ! str_contains($request->body(), 'google-apps.document'));
    }
}
