<?php

namespace Tests\Feature;

use App\Enums\Forms\ApprovalStatus;
use App\Helpers\FormFormatHelper;
use App\Jobs\SyncFormSubmissionToDriveJob;
use App\Models\FormSubmission;
use App\Models\GoogleDriveConnection;
use App\Models\Startup;
use App\Models\StartupDriveLink;
use App\Models\User;
use App\Support\Forms\StartupFormCatalog;
use App\Support\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_matches_the_source_form_ids_and_attachment_flags(): void
    {
        $forms = app(StartupFormCatalog::class)->all();

        $this->assertCount(21, $forms);
        $this->assertSame(
            [4, 5, 6, 18],
            array_values(array_map(
                static fn ($form): int => $form->id,
                array_filter($forms, static fn ($form): bool => $form->attachmentOnly)
            ))
        );
    }

    public function test_attachment_only_form_submission_stores_a_required_pdf_and_metadata(): void
    {
        Storage::fake('local');
        Startup::ensureConfigured();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_id' => 4,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
            'prerequisite' => UploadedFile::fake()->create('signed-agreement.pdf', 200, 'application/pdf'),
        ]);

        $response->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));
        $this->assertDatabaseHas('form_submissions', [
            'user_id' => $user->id,
            'form_id' => 4,
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'approval_status' => ApprovalStatus::Submitted->value,
            'prerequisite_original_name' => 'signed-agreement.pdf',
            'prerequisite_mime_type' => 'application/pdf',
        ]);

        $submission = FormSubmission::query()->firstOrFail();
        $this->assertStringStartsWith('form-prerequisites/agrisense-labs-form-4-', $submission->prerequisite_path);
        $this->assertTrue(Storage::disk('local')->exists($submission->prerequisite_path));
    }

    public function test_attachment_only_form_rejects_missing_or_non_pdf_prerequisite(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create();
        $payload = [
            'form_id' => 5,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => json_encode([], JSON_THROW_ON_ERROR),
        ];

        $this->actingAs($user)
            ->from('/incubatees')
            ->post(route('form-submissions.store'), $payload)
            ->assertSessionHasErrors('prerequisite');

        $this->actingAs($user)
            ->from('/incubatees')
            ->post(route('form-submissions.store'), [
                ...$payload,
                'prerequisite' => UploadedFile::fake()->create('agreement.txt', 20, 'text/plain'),
            ])
            ->assertSessionHasErrors('prerequisite');

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_non_attachment_form_can_be_submitted_without_a_prerequisite(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_id' => 2,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));

        $this->assertDatabaseHas('form_submissions', [
            'form_id' => 2,
            'prerequisite_path' => null,
            'approval_status' => ApprovalStatus::Submitted->value,
        ]);
    }

    public function test_submitting_a_form_dispatches_the_google_drive_sync_job(): void
    {
        Queue::fake();
        Startup::ensureConfigured();
        $user = User::factory()->create();
        GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_id' => 2,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));

        Queue::assertPushed(SyncFormSubmissionToDriveJob::class);
    }

    public function test_source_form_response_is_saved_for_the_external_track(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create();
        $data = [
            'source' => 'marian_TBI',
            'fields' => [
                ['label' => 'Innovation Title', 'type' => 'text', 'value' => 'Health access platform'],
                ['label' => 'Relevant Sector', 'type' => 'select-one', 'value' => 'Health'],
            ],
        ];

        $this->actingAs($user)
            ->post(route('form-submissions.store'), [
                'form_id' => 2,
                'startup' => 'LedgerLoop',
                'track' => 'external',
                'signer_name' => 'Carlo Tan',
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect(route('files', ['startup' => 'ledgerloop', 'track' => 'external']));

        $submission = FormSubmission::query()->firstOrFail();
        $this->assertSame([...$data, 'signer_name' => 'Carlo Tan'], $submission->data);
        $this->assertSame('external', $submission->track);
    }

    public function test_files_page_opens_the_startup_folder_and_lists_saved_submissions(): void
    {
        $user = User::factory()->create();
        FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Form 2 - Application Form and Innovation Profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['venture_name' => 'AgriSense Labs', 'summary' => 'Crop analytics'],
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $this->actingAs($user)
            ->get(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertOk()
            ->assertSee('AgriSense Labs')
            ->assertSee('Form 2 - Application Form and Innovation Profile')
            ->assertSee('Crop analytics');
    }

    public function test_files_page_lists_folders_for_each_incubatee(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('files'))
            ->assertOk()
            ->assertSee('agrisense-labs')
            ->assertSee('nanocure-lab')
            ->assertSee('borderpay')
            ->assertSee('@click="openFolder(folder.slug)"', false);
    }

    public function test_form_filler_does_not_show_the_removed_startup_details_step(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('incubatees'))
            ->assertOk()
            ->assertSee('startup-forms-root')
            ->assertSee(route('form-submissions.store'))
            ->assertSee('Form requirements')
            ->assertDontSee('Startup details');
    }

    public function test_startup_folder_lists_submitted_pdf_and_allows_authenticated_download(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $path = 'form-prerequisites/agrisense-labs-form-4-test.pdf';
        Storage::disk('local')->put($path, 'test pdf bytes');
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 4,
            'form_name' => 'Form 4 - Incubation Agreement (PDF Attachment)',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['signer_name' => 'Maria Santos'],
            'approval_status' => ApprovalStatus::Submitted,
            'prerequisite_path' => $path,
            'prerequisite_original_name' => 'signed-agreement.pdf',
            'prerequisite_mime_type' => 'application/pdf',
            'prerequisite_size_bytes' => 14,
        ]);

        $this->actingAs($user)
            ->get(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertOk()
            ->assertSee('signed-agreement.pdf');

        $this->actingAs($user)
            ->get(route('form-submissions.attachment', $submission))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=signed-agreement.pdf');
    }

    public function test_form_submission_requires_an_existing_startup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/incubatees')
            ->post(route('form-submissions.store'), [
                'form_id' => 2,
                'startup' => 'Unknown Venture',
                'track' => 'internal',
                'signer_name' => 'Maria Santos',
                'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
            ])
            ->assertSessionHasErrors('startup');

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_form_submission_is_blocked_when_the_user_cannot_access_the_startup_folder(): void
    {
        $manager = User::factory()->create();
        $otherMember = User::factory()->create();
        Startup::ensureConfigured();
        Startup::query()->findOrFail(1)->forceFill([
            'folder_access_restricted' => true,
            'folder_access_manager_id' => $manager->id,
        ])->save();

        $this->actingAs($otherMember)
            ->from('/incubatees')
            ->post(route('form-submissions.store'), [
                'form_id' => 2,
                'startup' => 'AgriSense Labs',
                'track' => 'internal',
                'signer_name' => 'Maria Santos',
                'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
            ])
            ->assertSessionHasErrors('startup');

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_form_submission_requires_mandatory_fields_and_valid_metadata(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create();
        $valid = [
            'form_id' => 2,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
        ];

        $withoutData = $valid;
        unset($withoutData['data']);
        $this->actingAs($user)
            ->from('/incubatees')
            ->post(route('form-submissions.store'), $withoutData)
            ->assertSessionHasErrors('data');

        $this->actingAs($user)
            ->from('/incubatees')
            ->post(route('form-submissions.store'), [...$valid, 'data' => '{invalid-json'])
            ->assertSessionHasErrors('data');

        $withoutSigner = $valid;
        unset($withoutSigner['signer_name']);
        $this->actingAs($user)
            ->from('/incubatees')
            ->post(route('form-submissions.store'), $withoutSigner)
            ->assertSessionHasErrors('signer_name');

        $this->actingAs($user)
            ->from('/incubatees')
            ->post(route('form-submissions.store'), [...$valid, 'form_id' => 99, 'track' => 'sideways'])
            ->assertSessionHasErrors(['form_id', 'track']);

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_json_submission_returns_validation_errors_for_missing_mandatory_fields(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('form-submissions.store'), [
                'form_id' => 2,
                'startup' => 'AgriSense Labs',
                'track' => 'internal',
                'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['signer_name']);

        $this->actingAs($user)
            ->postJson(route('form-submissions.store'), [
                'form_id' => 2,
                'startup' => 'AgriSense Labs',
                'track' => 'internal',
                'signer_name' => 'Maria Santos',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['data']);

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_non_attachment_form_rejects_an_uploaded_prerequisite(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/incubatees')
            ->post(route('form-submissions.store'), [
                'form_id' => 2,
                'startup' => 'AgriSense Labs',
                'track' => 'internal',
                'signer_name' => 'Maria Santos',
                'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
                'prerequisite' => UploadedFile::fake()->create('signed-agreement.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHasErrors('prerequisite');

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_a_submission_can_be_updated_with_new_data_and_replacement_attachment(): void
    {
        Storage::fake('local');
        Startup::ensureConfigured();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_id' => 4,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => json_encode([
                'founder' => 'Maria Santos',
                'evaluators' => [
                    ['name' => 'Kei Ramos', 'role' => 'Lead Reviewer', 'date' => '2026-01-02'],
                ],
            ], JSON_THROW_ON_ERROR),
            'prerequisite' => UploadedFile::fake()->create('signed-agreement.pdf', 200, 'application/pdf'),
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));

        $submission = FormSubmission::query()->firstOrFail();
        $oldPath = $submission->prerequisite_path;

        $response = $this->actingAs($user)->put(route('form-submissions.update', $submission), [
            'form_id' => 4,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => ['founder' => 'Maria Santos-Reyes'],
            'data_complex' => json_encode([
                'evaluators' => [
                    ['name' => 'Kei Ramos', 'role' => 'Lead Reviewer', 'date' => '2026-01-02'],
                ],
            ], JSON_THROW_ON_ERROR),
            'prerequisite' => UploadedFile::fake()->create('replacement-agreement.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));
        $response->assertSessionHas('status');

        $submission->refresh();
        $this->assertSame('Maria Santos-Reyes', $submission->data['founder']);
        $this->assertSame('Maria Santos', $submission->data['signer_name']);
        $this->assertSame('Kei Ramos', $submission->data['evaluators'][0]['name']);
        $this->assertSame('replacement-agreement.pdf', $submission->prerequisite_original_name);
        $this->assertNotSame($oldPath, $submission->prerequisite_path);
        $this->assertTrue(Storage::disk('local')->exists($submission->prerequisite_path));
        $this->assertFalse(Storage::disk('local')->exists($oldPath));
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_updating_a_submission_without_a_new_attachment_keeps_the_existing_file(): void
    {
        Storage::fake('local');
        Startup::ensureConfigured();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_id' => 4,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
            'prerequisite' => UploadedFile::fake()->create('signed-agreement.pdf', 200, 'application/pdf'),
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));

        $submission = FormSubmission::query()->firstOrFail();
        $oldPath = $submission->prerequisite_path;

        $this->actingAs($user)->put(route('form-submissions.update', $submission), [
            'form_id' => 4,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => ['founder' => 'Maria Santos (updated)'],
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));

        $submission->refresh();
        $this->assertSame('Maria Santos (updated)', $submission->data['founder']);
        $this->assertSame($oldPath, $submission->prerequisite_path);
        $this->assertSame('signed-agreement.pdf', $submission->prerequisite_original_name);
        $this->assertTrue(Storage::disk('local')->exists($oldPath));
    }

    public function test_updating_a_submission_cannot_move_it_to_another_incubatee(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_id' => 2,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));

        $submission = FormSubmission::query()->firstOrFail();

        $this->actingAs($user)->put(route('form-submissions.update', $submission), [
            'form_id' => 2,
            'startup' => 'LedgerLoop',
            'track' => 'external',
            'signer_name' => 'Maria Santos',
            'data' => ['founder' => 'Maria Santos'],
        ])->assertStatus(422);

        $this->assertSame('AgriSense Labs', $submission->fresh()->startup_name);
    }

    public function test_deleting_a_submission_removes_the_row_and_local_attachment(): void
    {
        Storage::fake('local');
        Startup::ensureConfigured();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_id' => 4,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
            'prerequisite' => UploadedFile::fake()->create('signed-agreement.pdf', 200, 'application/pdf'),
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));

        $submission = FormSubmission::query()->firstOrFail();
        $path = $submission->prerequisite_path;

        $this->actingAs($user)
            ->delete(route('form-submissions.destroy', $submission))
            ->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertSessionHas('status', 'Form submission deleted successfully.');

        $this->assertDatabaseCount('form_submissions', 0);
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_deleting_a_submission_removes_google_drive_copies_when_connected(): void
    {
        Storage::fake('local');
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
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
            'google_drive_response_file_id' => 'response-file-1',
            'google_drive_attachment_file_id' => 'attachment-file-1',
        ]);

        $deletedUrls = [];
        Http::fake(function (ClientRequest $request) use (&$deletedUrls) {
            if ($request->method() === 'DELETE') {
                $deletedUrls[] = $request->url();

                return Http::response(null, 204);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        $this->actingAs($user)
            ->delete(route('form-submissions.destroy', $submission))
            ->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertSessionHas('status', 'Form submission deleted successfully.');

        $this->assertDatabaseCount('form_submissions', 0);
        $this->assertCount(2, $deletedUrls);
        $this->assertStringContainsString('/files/response-file-1', $deletedUrls[0]);
        $this->assertStringContainsString('/files/attachment-file-1', $deletedUrls[1]);
    }

    public function test_moving_a_submission_to_a_custom_drive_folder_relocates_the_drive_files(): void
    {
        Storage::fake('local');
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
        Startup::query()->findOrFail(1)->forceFill([
            'google_drive_folder_id' => 'agrisense-folder',
            'google_drive_folder_url' => 'https://drive.google.com/drive/folders/agrisense-folder',
        ])->save();
        StartupDriveLink::query()->create([
            'startup_id' => 1,
            'startup_name' => 'AgriSense Labs',
            'label' => 'AgriSense R&D Drive',
            'url' => 'https://drive.google.com/drive/folders/custom-agri-folder',
            'user_id' => $user->id,
        ]);
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
            'google_drive_response_file_id' => 'response-file-1',
            'google_drive_attachment_file_id' => 'attachment-file-1',
        ]);

        $patchUrls = [];
        Http::fake(function (ClientRequest $request) use (&$patchUrls) {
            if ($request->method() === 'PATCH') {
                $patchUrls[] = $request->url();

                return Http::response(['id' => 'moved', 'parents' => ['custom-agri-folder']]);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        $this->actingAs($user)
            ->post(route('form-submissions.move', $submission), ['target_folder_id' => 'custom-agri-folder'])
            ->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertSessionHas('status', 'File moved to custom Drive folder successfully.');

        $submission->refresh();
        $this->assertSame('custom-agri-folder', $submission->google_drive_folder_id);
        $this->assertSame('AgriSense Labs', $submission->startup_name);
        $this->assertSame('internal', $submission->track);
        $this->assertNotNull($submission->drive_synced_at);
        $this->assertCount(2, $patchUrls);
        $this->assertStringContainsString('/files/response-file-1', $patchUrls[0]);
        $this->assertStringContainsString('/files/attachment-file-1', $patchUrls[1]);
        foreach ($patchUrls as $url) {
            $this->assertStringContainsString('addParents=custom-agri-folder', $url);
            $this->assertStringContainsString('removeParents=agrisense-folder', $url);
        }
    }

    public function test_moving_a_submission_validates_the_target_custom_drive_folder(): void
    {
        Storage::fake('local');
        Startup::ensureConfigured();
        $manager = User::factory()->create();
        $outsider = User::factory()->create();
        Startup::query()->findOrFail(1)->forceFill([
            'folder_access_restricted' => true,
            'folder_access_manager_id' => $manager->id,
            'google_drive_folder_id' => 'agrisense-folder',
            'google_drive_folder_url' => 'https://drive.google.com/drive/folders/agrisense-folder',
        ])->save();
        StartupDriveLink::query()->create([
            'startup_id' => 1,
            'startup_name' => 'AgriSense Labs',
            'label' => 'AgriSense R&D Drive',
            'url' => 'https://drive.google.com/drive/folders/agrisense-custom',
            'user_id' => $manager->id,
        ]);
        $submission = FormSubmission::query()->create([
            'user_id' => $manager->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
        ]);

        $this->actingAs($manager)
            ->post(route('form-submissions.move', $submission), ['target_folder_id' => 'foreign-folder'])
            ->assertStatus(422);

        $this->actingAs($manager)
            ->from(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->post(route('form-submissions.move', $submission), [])
            ->assertSessionHasErrors('target_folder_id');

        $this->actingAs($outsider)
            ->post(route('form-submissions.move', $submission), ['target_folder_id' => 'agrisense-custom'])
            ->assertStatus(403);

        $this->actingAs($manager)
            ->post(route('form-submissions.move', $submission), ['target_folder_id' => 'agrisense-custom'])
            ->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertSessionHas('status', 'File moved to custom Drive folder successfully.');

        $this->assertSame('agrisense-custom', $submission->fresh()->google_drive_folder_id);

        $this->actingAs($manager)
            ->post(route('form-submissions.move', $submission), ['target_folder_id' => 'agrisense-custom'])
            ->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertSessionHas('drive_sync_warning', 'The file is already in that Drive folder.');

        $this->assertSame('AgriSense Labs', $submission->fresh()->startup_name);
        $this->assertSame('agrisense-custom', $submission->fresh()->google_drive_folder_id);
    }

    public function test_deleting_a_submission_is_denied_without_folder_access(): void
    {
        Storage::fake('local');
        Startup::ensureConfigured();
        $manager = User::factory()->create();
        $otherMember = User::factory()->create();
        Startup::query()->findOrFail(1)->forceFill([
            'folder_access_restricted' => true,
            'folder_access_manager_id' => $manager->id,
        ])->save();

        $this->actingAs($manager)->post(route('form-submissions.store'), [
            'form_id' => 2,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
        ])->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));

        $submission = FormSubmission::query()->firstOrFail();

        $this->actingAs($otherMember)
            ->delete(route('form-submissions.destroy', $submission))
            ->assertStatus(403);

        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_files_page_renders_submission_data_as_human_readable_labels(): void
    {
        $user = User::factory()->create();
        FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Form 2 - Application Form and Innovation Profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => [
                'signer_name' => 'Maria Santos',
                'selectionOutcome' => 'Shortlisted',
                'scores' => [
                    'Innovation & Health-Tech Relevance' => '10/10',
                    'Feasibility' => '8/10',
                ],
                'evaluators' => [
                    ['name' => 'Kei Ramos', 'role' => 'Lead Reviewer', 'date' => '2026-01-02'],
                    ['name' => 'Ana Cruz', 'role' => 'Panelist', 'date' => '2026-01-03'],
                ],
                'sdgs' => ['Quality Education', 'Climate Action'],
            ],
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $this->actingAs($user)
            ->get(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']))
            ->assertOk()
            ->assertSee('Signer Name')
            ->assertSee('Maria Santos')
            ->assertSee('Selection Outcome')
            ->assertSee('Shortlisted')
            ->assertSee('Scores')
            ->assertSee('Health-Tech Relevance')
            ->assertSee('Evaluators')
            ->assertSee('Kei Ramos')
            ->assertSee('Lead Reviewer')
            ->assertSee('Quality Education')
            ->assertSee('Climate Action');

        $submission = FormSubmission::query()->firstOrFail();
        $preview = view('partials.submission-preview', ['submission' => $submission])->render();
        $this->assertStringContainsString('Kei Ramos', $preview);
        $this->assertStringContainsString('Lead Reviewer', $preview);
        $this->assertStringContainsString('Innovation &amp; Health-Tech Relevance', $preview);
        $this->assertStringContainsString('10/10', $preview);
        $this->assertStringNotContainsString('[{"', $preview);
        $this->assertStringNotContainsString('JSON.stringify', $preview);
    }

    public function test_form_format_helper_converts_keys_and_structures_into_display_blocks(): void
    {
        $this->assertSame('Signer Name', FormFormatHelper::toLabel('signer_name'));
        $this->assertSame('Selection Outcome', FormFormatHelper::toLabel('selectionOutcome'));

        $blocks = collect(FormFormatHelper::formatData([
            'signer_name' => 'Maria Santos',
            'scores' => ['innovation' => '10/10'],
            'evaluators' => [['name' => 'Kei', 'role' => 'Reviewer']],
            'sdgs' => ['Goal 4', 'Goal 13'],
            'emptyField' => '',
        ]))->keyBy('key');

        $this->assertSame('field', $blocks['signer_name']['type']);
        $this->assertSame('Maria Santos', $blocks['signer_name']['value']);
        $this->assertSame('grid', $blocks['scores']['type']);
        $this->assertSame([['key' => 'innovation', 'label' => 'Innovation', 'value' => '10/10']], $blocks['scores']['items']);
        $this->assertSame('table', $blocks['evaluators']['type']);
        $this->assertSame(['name' => 'Name', 'role' => 'Role'], $blocks['evaluators']['columns']);
        $this->assertSame('badges', $blocks['sdgs']['type']);
        $this->assertSame(['Goal 4', 'Goal 13'], $blocks['sdgs']['items']);
        $this->assertArrayNotHasKey('emptyField', $blocks->all());
    }

    public function test_form_submission_sync_targets_the_custom_drive_folder(): void
    {
        Storage::fake('local');
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
        Startup::query()->findOrFail(1)->forceFill([
            'google_drive_folder_id' => 'custom-folder-123',
            'google_drive_folder_url' => 'https://drive.google.com/drive/folders/custom-folder-123',
        ])->save();
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $captured = [];
        Http::fake(function (ClientRequest $request) use (&$captured) {
            $captured[] = $request;
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($request->method() === 'GET' && str_contains($path, '/permissions')) {
                return Http::response(['permissions' => []]);
            }
            if ($request->method() === 'POST' && str_contains($path, '/permissions')) {
                return Http::response(['id' => 'drive-permission']);
            }
            if ($request->method() === 'POST' && $path === '/upload/drive/v3/files') {
                return Http::response([
                    'id' => 'response-file',
                    'name' => 'form-response.json',
                    'webViewLink' => 'https://drive.google.com/file/d/response-file/view',
                ]);
            }

            return Http::response(['error' => 'Unexpected request: '.$request->method().' '.$request->url()], 500);
        });

        app(GoogleDriveService::class)->syncSubmission($submission);

        $folderCreates = array_filter(
            $captured,
            static fn (ClientRequest $request): bool => $request->method() === 'POST'
                && parse_url($request->url(), PHP_URL_PATH) === '/drive/v3/files'
        );
        $this->assertCount(0, $folderCreates);

        $permissionUrls = array_map(
            static fn (ClientRequest $request): string => $request->url(),
            array_filter($captured, static fn (ClientRequest $request): bool => str_contains($request->url(), '/permissions'))
        );
        $this->assertNotEmpty($permissionUrls);
        foreach ($permissionUrls as $url) {
            $this->assertStringContainsString('/files/custom-folder-123/permissions', $url);
        }

        $uploads = array_filter(
            $captured,
            static fn (ClientRequest $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/upload/drive/v3/files'
        );
        $this->assertNotEmpty($uploads);
        $this->assertStringContainsString('custom-folder-123', $uploads[array_key_first($uploads)]->body());

        $submission->refresh();
        $this->assertSame('response-file', $submission->google_drive_response_file_id);
        $this->assertNotNull($submission->drive_synced_at);
    }

    public function test_guest_cannot_submit_a_form(): void
    {
        $this->post(route('form-submissions.store'), [])->assertRedirect(route('login'));
    }
}
