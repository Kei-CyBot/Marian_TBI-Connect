<?php

namespace Tests\Feature;

use App\Mail\TeamInviteMail;
use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactIntakeTest extends TestCase
{
    use RefreshDatabase;

    private function application(array $overrides = []): ContactRegistrationApplication
    {
        $data = array_merge([
            'name' => 'Alex Rivera',
            'email' => 'alex.rivera@example.com',
            'phone' => '+1234567890',
            'stream' => 'External Industry',
            'affiliationType' => 'Company / Corporation',
            'affiliationOther' => '',
            'organization' => 'Rivera Labs',
            'role' => 'Industry Mentor',
            'roleOther' => '',
            'academicProgram' => '',
            'academicYear' => '',
            'domains' => ['Health / Life Sciences'],
            'domainOther' => '',
            'technicalSkills' => ['Data Analytics / Data Science'],
            'technicalOther' => '',
            'skillDetails' => 'Mentor experience',
        ], $overrides['data'] ?? []);

        return ContactRegistrationApplication::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'stream' => 'external',
            'application_data' => $data,
            ...array_diff_key($overrides, ['data' => true]),
        ]);
    }

    public function test_public_application_submission_creates_a_pending_application(): void
    {
        $response = $this->post(route('contact-registration.store'), [
            'stream' => 'external',
            'name' => 'Jordan Kim',
            'email' => 'jordan.kim@example.com',
            'phone' => '',
            'affiliation_type' => 'Company / Corporation',
            'affiliation_other' => '',
            'organization' => 'Kim Innovations',
            'role' => 'Industry Mentor',
            'role_other' => '',
            'academic_program' => '',
            'academic_year' => '',
            'domains' => ['Information Technology'],
            'domain_other' => '',
            'technical_skills' => ['Programming / Software Development'],
            'technical_other' => '',
            'skill_details' => '',
        ]);

        $response->assertRedirect(route('contact-registration'));

        $application = ContactRegistrationApplication::query()->where('email', 'jordan.kim@example.com')->sole();
        $this->assertSame('Pending', $application->status);
    }

    public function test_verify_converts_application_to_a_contact_record(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $application = $this->application();

        $this->actingAs($admin)
            ->patch(route('contact-intake.verify', $application))
            ->assertRedirect(route('contact-intake'));

        $this->assertDatabaseHas('contacts', [
            'email' => $application->email,
            'name' => $application->name,
            'verification_status' => 'Verified Contact',
        ]);

        $application->refresh();
        $this->assertSame('Verified', $application->status);
        $this->assertSame($admin->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);
    }

    public function test_approve_and_provision_creates_contact_and_dispatches_invite_mail(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $application = $this->application(['email' => 'provision@example.com']);

        $this->actingAs($admin)
            ->post(route('contact-intake.provision', $application))
            ->assertRedirect(route('contact-intake'));

        $this->assertDatabaseHas('contacts', [
            'email' => 'provision@example.com',
            'verification_status' => 'Verified Contact',
        ]);

        $invitation = TeamInvitation::query()->where('email', 'provision@example.com')->sole();
        $this->assertSame(User::ROLE_MEMBER_INCUBATEE, $invitation->role);
        $this->assertSame(64, strlen($invitation->token));
        $this->assertTrue($invitation->expires_at->between(now()->addDays(6), now()->addDays(8)));

        Mail::assertQueued(TeamInviteMail::class, fn (TeamInviteMail $mail): bool => $mail->hasTo('provision@example.com'));

        $application->refresh();
        $this->assertSame('Approved & Account Provisioned', $application->status);
        $this->assertSame($admin->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);
    }
}
