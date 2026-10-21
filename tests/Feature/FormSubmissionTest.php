<?php

namespace Tests\Feature;

use App\Enums\Forms\ApprovalStatus;
use App\Models\FormSubmission;
use App\Models\Startup;
use App\Models\User;
use App\Support\Forms\StartupFormCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_guest_cannot_submit_a_form(): void
    {
        $this->post(route('form-submissions.store'), [])->assertRedirect(route('login'));
    }
}
