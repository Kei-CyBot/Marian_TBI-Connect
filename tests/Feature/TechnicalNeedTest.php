<?php

namespace Tests\Feature;

use App\Mail\TechnicalNeedUpdatedAlert;
use App\Models\Contact;
use App\Models\FormSubmission;
use App\Models\Startup;
use App\Models\TechnicalNeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicalNeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_creation_sets_pending_without_files(): void
    {
        Startup::ensureConfigured();
        Storage::fake('local');
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($user)
            ->post(route('technical-needs.store'), [
                'startup_id' => 1,
                'requirement' => 'Cloud hosting credits',
                'date_entry' => '2026-10-01',
                'priority' => 'High',
            ])
            ->assertRedirect(route('technical-needs', ['startup_id' => 1]));

        $need = TechnicalNeed::query()->firstOrFail();

        $this->assertSame('pending', $need->status);
        $this->assertSame($user->id, $need->user_id);
        $this->assertNull($need->attachment_path);

        $this->actingAs($user)
            ->get(route('technical-needs', ['startup_id' => 1]))
            ->assertOk()
            ->assertSee('Technical Need Approval Form')
            ->assertSee('Attach &amp; Review', false)
            ->assertSee('Attach Institutional Approval Document (PDF, Word, Excel, max 10 MB)')
            ->assertSee('name="approval_document"', false)
            ->assertDontSee('name="attachment"', false)
            ->assertDontSee('Replace request attachment')
            ->assertDontSee('Uploading a new file replaces the attachment currently on file')
            ->assertDontSee('Upload attachment (PDF, Word, Excel, or image, max 10 MB)')
            ->assertDontSee('No attachment was submitted with this request.');
    }

    public function test_review_approves_a_request_with_attachment_and_notes(): void
    {
        Startup::ensureConfigured();
        Storage::fake('local');
        $reviewer = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $need = TechnicalNeed::query()->create([
            'startup_id' => 1,
            'requirement' => 'Lab equipment',
            'date_entry' => '2026-10-01',
            'status' => 'pending',
        ]);

        $this->actingAs($reviewer)
            ->post(route('technical-needs.review', $need), [
                'action' => 'approved',
                'approval_document' => UploadedFile::fake()->create('approval.pdf', 10, 'application/pdf'),
                'review_notes' => 'Approved by the technical committee.',
            ])
            ->assertRedirect(route('technical-needs', ['startup_id' => 1]));

        $need->refresh();

        $this->assertSame('approved', $need->status);
        $this->assertSame($reviewer->id, $need->reviewed_by);
        $this->assertNotNull($need->reviewed_at);
        $this->assertSame('Approved by the technical committee.', $need->review_notes);
        $this->assertNotNull($need->approval_attachment_path);
        $this->assertTrue(Storage::disk('local')->exists($need->approval_attachment_path));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'technical_need_reviewed',
            'subject_id' => $need->id,
            'subject_type' => TechnicalNeed::class,
        ]);

        $this->actingAs($reviewer)
            ->get(route('technical-needs.view-document', ['technicalNeed' => $need, 'document' => 'approval']))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline');
    }

    public function test_review_rejects_a_request_without_an_attachment(): void
    {
        Startup::ensureConfigured();
        Storage::fake('local');
        $reviewer = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $need = TechnicalNeed::query()->create([
            'startup_id' => 1,
            'requirement' => 'Lab equipment',
            'date_entry' => '2026-10-01',
            'status' => 'pending',
        ]);

        $this->actingAs($reviewer)
            ->post(route('technical-needs.review', $need), [
                'action' => 'rejected',
                'review_notes' => 'Budget is unavailable this quarter.',
            ])
            ->assertRedirect(route('technical-needs', ['startup_id' => 1]));

        $need->refresh();

        $this->assertSame('rejected', $need->status);
        $this->assertSame($reviewer->id, $need->reviewed_by);
        $this->assertNull($need->approval_attachment_path);
    }

    public function test_review_rejects_an_unsupported_action(): void
    {
        Startup::ensureConfigured();
        Storage::fake('local');
        $reviewer = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $need = TechnicalNeed::query()->create([
            'startup_id' => 1,
            'requirement' => 'Lab equipment',
            'date_entry' => '2026-10-01',
            'status' => 'pending',
        ]);

        $this->actingAs($reviewer)
            ->from(route('technical-needs', ['startup_id' => 1]))
            ->post(route('technical-needs.review', $need), ['action' => 'maybe'])
            ->assertSessionHasErrors('action');

        $this->assertSame('pending', $need->refresh()->status);
    }

    public function test_authenticated_team_member_can_create_edit_and_delete_a_startup_technical_need(): void
    {
        $user = User::factory()->create();
        Startup::ensureConfigured();
        Storage::fake('local');
        $payload = [
            'startup_id' => 1,
            'requirement' => 'Cloud hosting credits',
            'description' => 'Compute for the pilot.',
            'subscription_price' => '2500.00',
            'months_needed' => 6,
            'responsible_team_member' => 'Mia Santos',
            'date_entry' => '2026-10-01',
            'date_needed' => '2026-11-01',
            'resource_tool' => 'Cloud credits',
            'prospect_resource_person' => 'University IT Services',
            'priority' => 'High',
            'remarks' => 'Confirm eligibility.',
        ];

        $this->actingAs($user)
            ->get(route('technical-needs'))
            ->assertOk()
            ->assertSee('Incubatee technical-needs folders')
            ->assertDontSee('All incubatees')
            ->assertDontSee('View all needs')
            ->assertDontSee('Price / subscription (PHP)')
            ->assertDontSee('No technical needs in this folder yet');

        $this->actingAs($user)
            ->get(route('technical-needs', ['startup_id' => 1]))
            ->assertOk()
            ->assertSee('Add Technical Need')
            ->assertSee('Technical Need Approval Form')
            ->assertSee('Price / subscription (PHP)')
            ->assertSee('Submit for review')
            ->assertSee('window.technicalNeedsPage');

        $this->actingAs($user)
            ->post(route('technical-needs.store'), $payload)
            ->assertRedirect(route('technical-needs', ['startup_id' => 1]));

        $need = TechnicalNeed::query()->firstOrFail();
        $this->assertSame('2500.00', $need->subscription_price);
        $this->assertSame(1, $need->startup_id);
        $this->assertSame('pending', $need->status);
        $this->assertNull($need->attachment_path);

        $this->actingAs($user)
            ->put(route('technical-needs.update', $need), [...$payload, 'requirement' => 'Updated hosting credits'])
            ->assertRedirect(route('technical-needs', ['startup_id' => 1]));

        $this->assertSame('Updated hosting credits', $need->refresh()->requirement);

        $this->actingAs($user)
            ->delete(route('technical-needs.destroy', $need))
            ->assertRedirect(route('technical-needs', ['startup_id' => 1]));

        $this->assertDatabaseMissing('technical_needs', ['id' => $need->id]);
    }

    public function test_updating_a_need_without_a_new_attachment_keeps_the_existing_document(): void
    {
        Mail::fake();
        Startup::ensureConfigured();
        Storage::fake('local');
        User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create();
        $payload = [
            'startup_id' => 1,
            'requirement' => 'Lab equipment',
            'date_entry' => '2026-10-01',
            'priority' => 'Moderate',
        ];
        $oldAttachmentPath = 'technical-needs/1/attachments/specification.pdf';
        Storage::disk('local')->put($oldAttachmentPath, '%PDF-1.4');
        $need = TechnicalNeed::query()->create($payload + ['attachment_path' => $oldAttachmentPath]);

        $this->actingAs($user)
            ->put(route('technical-needs.update', $need), [...$payload, 'requirement' => 'Updated lab equipment'])
            ->assertRedirect(route('technical-needs', ['startup_id' => 1]));

        $need->refresh();
        $this->assertSame('Updated lab equipment', $need->requirement);
        $this->assertSame($oldAttachmentPath, $need->attachment_path);
        $this->assertTrue(Storage::disk('local')->exists($oldAttachmentPath));
        Mail::assertQueued(TechnicalNeedUpdatedAlert::class);
    }

    public function test_updating_a_need_with_a_new_attachment_replaces_the_existing_document(): void
    {
        Mail::fake();
        Startup::ensureConfigured();
        Storage::fake('local');
        User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create();
        $payload = [
            'startup_id' => 1,
            'requirement' => 'Lab equipment',
            'date_entry' => '2026-10-01',
            'priority' => 'Moderate',
        ];
        $oldAttachmentPath = 'technical-needs/1/attachments/specification.pdf';
        Storage::disk('local')->put($oldAttachmentPath, '%PDF-1.4');
        $need = TechnicalNeed::query()->create($payload + ['attachment_path' => $oldAttachmentPath]);

        $this->actingAs($user)
            ->put(route('technical-needs.update', $need), [
                ...$payload,
                'requirement' => 'Updated lab equipment',
                'attachment' => UploadedFile::fake()->create('replacement-specification.pdf', 12, 'application/pdf'),
            ])
            ->assertRedirect(route('technical-needs', ['startup_id' => 1]));

        $need->refresh();
        $this->assertSame('Updated lab equipment', $need->requirement);
        $this->assertNotSame($oldAttachmentPath, $need->attachment_path);
        $this->assertTrue(Storage::disk('local')->exists($need->attachment_path));
        $this->assertFalse(Storage::disk('local')->exists($oldAttachmentPath));
        Mail::assertQueued(TechnicalNeedUpdatedAlert::class);
    }

    public function test_need_date_cannot_precede_its_entry_date(): void
    {
        Startup::ensureConfigured();

        $this->actingAs(User::factory()->create())
            ->from(route('technical-needs'))
            ->post(route('technical-needs.store'), [
                'startup_id' => 1,
                'requirement' => 'Lab equipment',
                'date_entry' => '2026-10-10',
                'date_needed' => '2026-10-09',
                'status' => 'Ongoing',
                'priority' => 'Moderate',
            ])
            ->assertSessionHasErrors('date_needed');

        $this->assertDatabaseCount('technical_needs', 0);
    }

    public function test_technical_needs_are_separated_into_a_folder_for_each_incubatee(): void
    {
        Startup::ensureConfigured();
        $first = Startup::query()->findOrFail(1);
        $second = Startup::query()->create([
            'name' => 'Independent Startup',
            'founder' => 'Jordan Lee',
            'contact_email' => 'jordan@example.test',
            'track' => 'external',
            'status' => 'Incubatee',
            'cohort_id' => 1,
        ]);
        TechnicalNeed::query()->create([
            'startup_id' => $first->id,
            'requirement' => 'First startup equipment',
            'date_entry' => '2026-10-01',
        ]);
        TechnicalNeed::query()->create([
            'startup_id' => $second->id,
            'requirement' => 'Second startup software',
            'date_entry' => '2026-10-02',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('technical-needs'))
            ->assertOk()
            ->assertSee('Incubatee technical-needs folders')
            ->assertSee('Open an incubatee folder to view and manage its technical needs.')
            ->assertViewHas('requirements', [])
            ->assertViewHas('startups', function (array $startups) use ($first, $second): bool {
                $folders = collect($startups)->keyBy('id');

                return $folders[$first->id]['technicalNeedCount'] === 1
                    && $folders[$second->id]['technicalNeedCount'] === 1;
            });
    }

    public function test_incubatees_page_has_internal_and_external_track_filter(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('incubatees'))
            ->assertOk()
            ->assertSee('Filter by incubatee track')
            ->assertSee('Internal Academic')
            ->assertSee('External Industry');
    }

    public function test_approval_download_obeys_incubatee_folder_access(): void
    {
        Startup::ensureConfigured();
        Storage::fake('local');
        $manager = User::factory()->create();
        $otherMember = User::factory()->create();
        $startup = Startup::query()->findOrFail(1);
        $startup->forceFill([
            'folder_access_restricted' => true,
            'folder_access_manager_id' => $manager->id,
        ])->save();
        $need = TechnicalNeed::query()->create([
            'startup_id' => $startup->id,
            'requirement' => 'Restricted technical need',
            'date_entry' => '2026-10-01',
            'approval_attachment_path' => 'technical-needs/1/approval.pdf',
        ]);
        Storage::disk('local')->put($need->approval_attachment_path, '%PDF-1.4');

        $this->actingAs($otherMember)
            ->get(route('technical-needs.approval', $need))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('technical-needs.approval', $need))
            ->assertOk();
    }

    public function test_new_incubatee_founder_is_added_as_a_pending_contact_and_linked(): void
    {
        $user = User::factory()->create();
        Startup::ensureConfigured();

        $this->actingAs($user)
            ->post(route('incubatees.store'), [
                'name' => 'Marian BioLabs',
                'founder' => 'Ana Reyes',
                'email' => 'ana@example.test',
                'track' => 'internal',
                'status' => 'Applicant',
                'cohort_id' => 1,
                'venture_type' => 'Research Lab',
            ])
            ->assertRedirect(route('incubatees'));

        $startup = Startup::query()->where('name', 'Marian BioLabs')->firstOrFail();
        $contact = Contact::query()->where('email', 'ana@example.test')->firstOrFail();

        $this->assertSame($contact->id, $startup->founder_contact_id);
        $this->assertSame('Founder', $contact->role);
        $this->assertSame('Pending Verification', $contact->verification_status);
    }

    public function test_dashboard_tallies_uploaded_files_and_technical_need_statistics(): void
    {
        Startup::ensureConfigured();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $startup = Startup::query()->findOrFail(1);

        TechnicalNeed::query()->create([
            'startup_id' => $startup->id,
            'requirement' => 'Cloud hosting',
            'date_entry' => '2026-10-01',
            'priority' => 'High',
            'status' => 'pending',
            'subscription_price' => '1500.00',
            'resource_tool' => 'Cloud credits',
            'attachment_path' => 'technical-needs/1/cloud.pdf',
        ]);
        TechnicalNeed::query()->create([
            'startup_id' => $startup->id,
            'requirement' => 'Workstation',
            'date_entry' => '2026-10-02',
            'priority' => 'Low',
            'status' => 'approved',
            'subscription_price' => '500.00',
        ]);
        FormSubmission::query()->create([
            'user_id' => $admin->id,
            'form_id' => 1,
            'form_name' => 'Startup Profile',
            'startup_name' => $startup->name,
            'track' => 'internal',
            'data' => [],
            'approval_status' => 'submitted',
            'local_path' => 'submissions/profile.pdf',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total Uploaded Files')
            ->assertSee('Technical Needs Summary')
            ->assertSee('Resource & Tool Allocations')
            ->assertViewHas('metrics', static function (array $metrics): bool {
                return $metrics['uploadedFiles']['total'] === 2
                    && $metrics['uploadedFiles']['technicalNeeds'] === 1
                    && $metrics['uploadedFiles']['repository'] === 1
                    && $metrics['technicalNeeds']['total'] === 2
                    && $metrics['technicalNeeds']['pending'] === 1
                    && $metrics['technicalNeeds']['priorities'] === ['Low' => 1, 'Moderate' => 0, 'High' => 1]
                    && $metrics['resourceAllocations']['total'] === 1
                    && (float) $metrics['resourceAllocations']['estimatedCost'] === 2000.0;
            });
    }

    public function test_technical_needs_support_server_side_filtering_and_sorting(): void
    {
        Startup::ensureConfigured();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $startup = Startup::query()->findOrFail(1);

        foreach ([
            ['requirement' => 'Alpha', 'subscription_price' => '100.00', 'priority' => 'Low', 'date_entry' => '2026-10-01', 'date_needed' => '2026-12-01', 'responsible_team_member' => 'Mia Santos'],
            ['requirement' => 'Beta', 'subscription_price' => '300.00', 'priority' => 'High', 'date_entry' => '2026-10-02', 'date_needed' => '2026-11-01', 'responsible_team_member' => 'Ana Cruz'],
            ['requirement' => 'Gamma', 'subscription_price' => '200.00', 'priority' => 'Moderate', 'date_entry' => '2026-10-03', 'date_needed' => '2026-10-20', 'responsible_team_member' => 'Mia Santos'],
        ] as $attributes) {
            TechnicalNeed::query()->create($attributes + ['startup_id' => $startup->id]);
        }

        $this->actingAs($admin)
            ->get(route('technical-needs', ['startup_id' => $startup->id, 'price_min' => 150, 'price_max' => 250]))
            ->assertOk()
            ->assertViewHas('requirements', fn (array $requirements): bool => collect($requirements)->pluck('requirement')->all() === ['Gamma']);

        $this->actingAs($admin)
            ->get(route('technical-needs', ['startup_id' => $startup->id, 'priority' => 'High']))
            ->assertOk()
            ->assertViewHas('requirements', fn (array $requirements): bool => collect($requirements)->pluck('requirement')->all() === ['Beta']);

        $this->actingAs($admin)
            ->get(route('technical-needs', ['startup_id' => $startup->id, 'date_needed_from' => '2026-11-01', 'date_needed_to' => '2026-11-30']))
            ->assertOk()
            ->assertViewHas('requirements', fn (array $requirements): bool => collect($requirements)->pluck('requirement')->all() === ['Beta']);

        $this->actingAs($admin)
            ->get(route('technical-needs', ['startup_id' => $startup->id, 'responsible_team_member' => 'Mia Santos']))
            ->assertOk()
            ->assertViewHas('requirements', fn (array $requirements): bool => collect($requirements)->pluck('requirement')->all() === ['Gamma', 'Alpha']);

        $this->actingAs($admin)
            ->get(route('technical-needs', ['startup_id' => $startup->id, 'sort_by' => 'price_subscription', 'sort_order' => 'asc']))
            ->assertOk()
            ->assertViewHas('requirements', fn (array $requirements): bool => collect($requirements)->pluck('requirement')->all() === ['Alpha', 'Gamma', 'Beta']);

        $this->actingAs($admin)
            ->get(route('technical-needs', ['startup_id' => $startup->id, 'sort_by' => 'title', 'sort_order' => 'desc']))
            ->assertOk()
            ->assertViewHas('requirements', fn (array $requirements): bool => collect($requirements)->pluck('requirement')->all() === ['Gamma', 'Beta', 'Alpha']);
    }

    public function test_technical_needs_page_renders_card_view_with_filter_and_sort_controls(): void
    {
        Startup::ensureConfigured();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $startup = Startup::query()->findOrFail(1);

        TechnicalNeed::query()->create([
            'startup_id' => $startup->id,
            'requirement' => 'Card Alpha',
            'date_entry' => '2026-10-01',
            'priority' => 'High',
            'subscription_price' => '100.00',
        ]);
        TechnicalNeed::query()->create([
            'startup_id' => $startup->id,
            'requirement' => 'Card Beta',
            'date_entry' => '2026-10-02',
            'priority' => 'Low',
            'subscription_price' => '300.00',
        ]);

        $this->actingAs($admin)
            ->get(route('technical-needs', [
                'startup_id' => $startup->id,
                'priority' => 'High',
                'sort_by' => 'title',
                'sort_order' => 'asc',
            ]))
            ->assertOk()
            ->assertSee('Filter &amp; Search', false)
            ->assertSee('Sort by')
            ->assertSee('More request details')
            ->assertSee('Card Alpha')
            ->assertDontSee('Card Beta')
            ->assertViewHas('requirements', fn (array $requirements): bool => collect($requirements)->pluck('requirement')->all() === ['Card Alpha'])
            ->assertViewHas('sortBy', 'title')
            ->assertViewHas('sortOrder', 'asc');
    }

    public function test_priority_filter_and_technical_need_csv_export_still_work(): void
    {
        Startup::ensureConfigured();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $startup = Startup::query()->findOrFail(1);

        foreach (['Low', 'Moderate', 'High'] as $index => $priority) {
            TechnicalNeed::query()->create([
                'startup_id' => $startup->id,
                'requirement' => $priority.' need',
                'date_entry' => '2026-10-0'.($index + 1),
                'priority' => $priority,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('technical-needs', ['startup_id' => $startup->id, 'priority' => 'Moderate']))
            ->assertOk()
            ->assertViewHas('requirements', fn (array $requirements): bool => collect($requirements)->pluck('priority')->unique()->all() === ['Moderate']);

        $response = $this->actingAs($admin)->get(route('exports.technical-needs'));
        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Priority', $content);
        $this->assertStringContainsString('Moderate', $content);
    }

    public function test_events_page_includes_create_event_action_and_calendar_details(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('events'))
            ->assertOk()
            ->assertSee('+ Create Event')
            ->assertSee('Other (custom message)')
            ->assertSee('calendar-event-title')
            ->assertDontSee('Insert variable:')
            ->assertDontSee('target_audience');
    }
}
