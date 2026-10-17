<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_startup_can_be_archived_and_restored_without_losing_its_record(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('incubatees'))->assertOk();
        $startup = Startup::query()->findOrFail(1);

        $this->actingAs($user)
            ->delete(route('incubatees.destroy', $startup))
            ->assertRedirect(route('incubatees'))
            ->assertSessionHas('startup_status');

        $this->assertSoftDeleted('startups', ['id' => $startup->id]);
        $this->assertDatabaseHas('startups', ['id' => $startup->id, 'archived_by' => $user->id]);
        $this->assertSame(7, Startup::query()->count());
        $this->actingAs($user)->get(route('incubatees'))->assertOk()->assertDontSee($startup->contact_email);
        $this->actingAs($user)->get(route('archives'))->assertOk()->assertSee('AgriSense Labs');

        $this->actingAs($user)
            ->post(route('archives.startups.restore', $startup->id))
            ->assertRedirect(route('archives'));

        $this->assertNotSoftDeleted('startups', ['id' => $startup->id]);
        $this->actingAs($user)->get(route('incubatees'))->assertSee('AgriSense Labs');
    }

    public function test_startups_can_be_created_and_updated_in_persistent_storage(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('incubatees'))->assertOk();

        $this->actingAs($user)->post(route('incubatees.store'), [
            'name' => 'New Venture',
            'founder' => 'Taylor Founder',
            'email' => 'taylor@example.com',
            'track' => 'external',
            'status' => 'Applicant',
            'cohort_id' => 2,
            'venture_type' => 'Commercial Startup',
        ])->assertRedirect(route('incubatees'));

        $startup = Startup::query()->where('name', 'New Venture')->firstOrFail();
        $this->actingAs($user)->put(route('incubatees.update', $startup), [
            'name' => 'Updated Venture',
            'founder' => 'Taylor Founder',
            'email' => 'taylor@example.com',
            'track' => 'external',
            'status' => 'Incubatee',
            'cohort_id' => 2,
            'venture_type' => 'Commercial Startup',
        ])->assertRedirect(route('incubatees'));

        $this->assertSame('Updated Venture', $startup->refresh()->name);
        $this->assertSame('Incubatee', $startup->status);
    }

    public function test_contact_can_be_archived_and_restored(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('contacts'))->assertOk();
        $contact = Contact::query()->findOrFail(1);

        $this->actingAs($user)
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('contacts'))
            ->assertSessionHas('contact_status');

        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'archived_by' => $user->id]);
        $this->assertSame(6, Contact::query()->count());
        $this->actingAs($user)->get(route('contacts'))->assertOk()->assertDontSee($contact->email);
        $this->actingAs($user)->get(route('archives'))->assertOk()->assertSee('Dr. Elena Cruz');

        $this->actingAs($user)
            ->post(route('archives.contacts.restore', $contact->id))
            ->assertRedirect(route('archives'));

        $this->assertNotSoftDeleted('contacts', ['id' => $contact->id]);
        $this->actingAs($user)->get(route('contacts'))->assertSee('Dr. Elena Cruz');
    }

    public function test_contact_intake_contains_a_submission_preview(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('contact-intake'))
            ->assertOk()
            ->assertSee('Preview application')
            ->assertSee('Delete application')
            ->assertSee('aria-modal="true"', false)
            ->assertSee('Tools, languages or methods')
            ->assertSee('does not create a login')
            ->assertSee('Add Incubatee');
    }

    public function test_public_contact_registration_is_saved_reviewed_and_deletable(): void
    {
        $user = User::factory()->create();
        $payload = [
            'stream' => 'internal',
            'name' => 'Mika Santos',
            'email' => 'mika.santos@example.edu',
            'phone' => '+63 917 555 0104',
            'affiliation_type' => 'University department / office',
            'organization' => 'College of Computing',
            'role' => 'Student Lead',
            'academic_program' => 'BS Computer Science',
            'academic_year' => '3rd year',
            'domains' => ['Information Technology'],
            'technical_skills' => ['Web Development'],
            'skill_details' => 'Python and Laravel',
        ];

        $this->post(route('contact-registration.store'), $payload)
            ->assertRedirect(route('contact-registration'))
            ->assertSessionHas('registration_status');

        $this->assertDatabaseHas('contact_registration_applications', [
            'email' => 'mika.santos@example.edu',
            'status' => 'Pending',
        ]);
        $application = ContactRegistrationApplication::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('contact-intake'))
            ->assertOk()
            ->assertSee('Mika Santos');

        $this->actingAs($user)
            ->patch(route('contact-intake.verify', $application))
            ->assertRedirect(route('contact-intake'))
            ->assertSessionHas('intake_status');

        $this->assertDatabaseHas('contact_registration_applications', [
            'id' => $application->id,
            'status' => 'Verified',
            'reviewed_by' => $user->id,
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'mika.santos@example.edu']);

        $this->actingAs($user)
            ->delete(route('contact-intake.destroy', $application))
            ->assertRedirect(route('contact-intake'));

        $this->assertDatabaseMissing('contact_registration_applications', ['id' => $application->id]);
    }

    public function test_contact_can_be_created_and_updated_in_persistent_storage(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('contacts'))->assertOk();

        $payload = [
            'side' => 'external',
            'name' => 'Taylor Example',
            'email' => 'taylor@example.com',
            'phone' => '+63 917 123 4567',
            'affiliation_type' => 'Freelancer / Self-employed',
            'role' => 'Industry Mentor',
            'organization' => '',
            'domains' => ['Information Technology'],
            'technical_skills' => ['Web Development'],
            'nda_status' => 'Pending',
        ];

        $this->actingAs($user)->post(route('contacts.store'), $payload)->assertRedirect(route('contacts'));
        $contact = Contact::query()->where('email', 'taylor@example.com')->firstOrFail();
        $this->assertSame('+63 917 123 4567', $contact->phone);

        $this->actingAs($user)->put(route('contacts.update', $contact), [
            ...$payload,
            'name' => 'Taylor Updated',
        ])->assertRedirect(route('contacts'));

        $this->assertSame('Taylor Updated', $contact->refresh()->name);
    }

    public function test_contact_can_be_assigned_to_an_incubatee_with_review_evidence(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('contacts'))->assertOk();
        $contact = Contact::query()->findOrFail(1);
        $startup = Startup::query()->findOrFail(1);

        $this->actingAs($user)
            ->post(route('contacts.assignments.store', $contact), [
                'startup_id' => $startup->id,
                'verification_method' => 'founder_email_match',
                'verification_notes' => 'Confirmed against the registered startup email.',
            ])
            ->assertRedirect(route('contacts'))
            ->assertSessionHasErrors('verification_method');

        $this->actingAs($user)
            ->post(route('contacts.assignments.store', $contact), [
                'startup_id' => $startup->id,
                'verification_method' => 'program_records',
                'verification_notes' => 'Matched the signed incubatee intake record on file.',
            ])
            ->assertRedirect(route('contacts'))
            ->assertSessionHas('contact_status');

        $this->assertDatabaseHas('contact_startup_assignments', [
            'contact_id' => $contact->id,
            'startup_id' => $startup->id,
            'assigned_by' => $user->id,
            'confirmed_by' => $user->id,
            'verification_method' => 'program_records',
            'verification_notes' => 'Matched the signed incubatee intake record on file.',
        ]);

        $this->actingAs($user)
            ->get(route('contacts'))
            ->assertOk()
            ->assertSee('Manage incubatee assignments')
            ->assertSee('AgriSense Labs')
            ->assertSee('Matched the signed incubatee intake record on file.');

        $this->actingAs($user)
            ->delete(route('contacts.assignments.destroy', [$contact, $startup]))
            ->assertRedirect(route('contacts'));

        $this->assertDatabaseMissing('contact_startup_assignments', [
            'contact_id' => $contact->id,
            'startup_id' => $startup->id,
        ]);
    }
}
