<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Startup;
use App\Models\TechnicalNeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicalNeedTest extends TestCase
{
    use RefreshDatabase;

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
            'status' => 'Ongoing',
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
            ->assertSee('Price / subscription (PHP)')
            ->assertSee('Print approval form')
            ->assertSee('Upload signed approval')
            ->assertSee('window.technicalNeedsPage')
            ->assertDontSee("String(value || '').replace")
            ->assertDontSee('printWindow.document.write');
        $this->assertStringNotContainsString('window.onload=()=>window.print()', file_get_contents(resource_path('views/technical-needs.blade.php')));
        $this->assertStringContainsString('onclick="window.print()"', file_get_contents(resource_path('js/app.js')));

        $this->actingAs($user)
            ->post(route('technical-needs.store'), $payload + [
                'signed_approval' => UploadedFile::fake()->create('signed-approval.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect(route('technical-needs', ['startup_id' => 1]));

        $need = TechnicalNeed::query()->firstOrFail();
        $this->assertSame('2500.00', $need->subscription_price);
        $this->assertSame(1, $need->startup_id);
        $oldApprovalPath = $need->approval_document_path;
        $this->assertTrue(Storage::disk('local')->exists($need->approval_document_path));
        $this->actingAs($user)
            ->get(route('technical-needs.approval', $need))
            ->assertOk();

        $this->actingAs($user)
            ->put(route('technical-needs.update', $need), [...$payload, 'requirement' => 'Updated hosting credits', 'signed_approval' => UploadedFile::fake()->create('updated-signed-approval.pdf', 10, 'application/pdf')])
            ->assertRedirect(route('technical-needs', ['startup_id' => 1]));

        $this->assertSame('Updated hosting credits', $need->refresh()->requirement);
        $this->assertFalse(Storage::disk('local')->exists($oldApprovalPath));
        $this->assertTrue(Storage::disk('local')->exists($need->approval_document_path));

        $this->actingAs($user)
            ->delete(route('technical-needs.destroy', $need))
            ->assertRedirect(route('technical-needs', ['startup_id' => 1]));

        $this->assertDatabaseMissing('technical_needs', ['id' => $need->id]);
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

    public function test_signed_approval_pdf_is_required_before_a_need_can_be_saved(): void
    {
        Startup::ensureConfigured();
        Storage::fake('local');
        $payload = [
            'startup_id' => 1,
            'requirement' => 'Lab equipment',
            'date_entry' => '2026-10-10',
            'status' => 'Ongoing',
            'priority' => 'Moderate',
        ];

        $this->actingAs(User::factory()->create())
            ->from(route('technical-needs', ['startup_id' => 1]))
            ->post(route('technical-needs.store'), $payload)
            ->assertSessionHasErrors('signed_approval');

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

    public function test_signed_approval_download_obeys_incubatee_folder_access(): void
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
            'approval_document_path' => 'technical-needs/1/approval.pdf',
            'approval_document_original_name' => 'approval.pdf',
        ]);
        Storage::disk('local')->put($need->approval_document_path, '%PDF-1.4');

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

    public function test_events_include_a_custom_event_type_and_target_cohort_explanation(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('events'))
            ->assertOk()
            ->assertSee('Other')
            ->assertSee('Other (custom message)')
            ->assertSee('calendar-event-title')
            ->assertDontSee('Insert variable:')
            ->assertSee('It does not change incubatee membership or automatically send invitations or filter RSVPs.');
    }
}
