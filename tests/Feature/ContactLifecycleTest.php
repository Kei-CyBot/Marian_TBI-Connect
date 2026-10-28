<?php

namespace Tests\Feature;

use App\Mail\ContactRegistrationNotificationMail;
use App\Mail\ContactRegistrationReceiptMail;
use App\Mail\UserCredentialInviteMail;
use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactLifecycleTest extends TestCase
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

    public function test_registration_creates_pending_intake_application_and_leaves_contacts_empty(): void
    {
        Mail::fake();
        $contactsBefore = Contact::count();

        $response = $this->post(route('contact-registration.store'), [
            'stream' => 'external',
            'name' => 'Jordan Kim',
            'email' => 'jordan.kim@example.com',
        ]);

        $response->assertRedirect(route('contact-registration'));

        $application = ContactRegistrationApplication::query()->where('email', 'jordan.kim@example.com')->sole();
        $this->assertSame('Pending', $application->status);
        $this->assertSame($contactsBefore, Contact::count());
    }

    public function test_approval_updates_application_to_approved_removes_from_pending_intake_and_creates_contact(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $application = $this->application(['email' => 'approve@example.com']);
        $contactsBefore = Contact::count();

        $this->actingAs($admin)
            ->post(route('contact-intake.provision', $application))
            ->assertRedirect(route('contact-intake'));

        $application->refresh();
        $this->assertSame('Approved', $application->status);

        $this->assertSame($contactsBefore + 1, Contact::count());
        $this->assertDatabaseHas('contacts', [
            'email' => 'approve@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('contact-intake'));
        $response->assertOk();
    }

    public function test_approval_creates_user_with_eight_digit_hashed_password_and_queues_provisioning_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $application = $this->application(['email' => 'provisioned2@example.com']);
        $usersBefore = User::count();

        $this->actingAs($admin)
            ->post(route('contact-intake.provision', $application))
            ->assertRedirect(route('contact-intake'));

        $this->assertSame($usersBefore, User::count());
        Mail::assertNothingQueued();
    }

    public function test_super_admin_can_update_contact_user_email_and_password(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $contact = Contact::create([
            'side' => 'external',
            'name' => 'Sam Lee',
            'email' => 'sam.lee@example.com',
            'affiliation_type' => 'Company / Corporation',
            'role' => 'Industry Mentor',
            'verification_status' => 'Verified Contact',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
        ]);
        $user = User::factory()->create([
            'name' => 'Sam Lee',
            'email' => 'sam.lee@example.com',
            'role' => 'member_incubatee',
        ]);

        $this->actingAs($superAdmin)
            ->patch(route('contacts.credentials.update', $contact), [
                'email' => 'sam.new@example.com',
                'password' => 'newpassword123',
            ])
            ->assertRedirect(route('contacts'));

        $user->refresh();
        $contact->refresh();
        $this->assertSame('sam.new@example.com', $user->email);
        $this->assertSame('sam.new@example.com', $contact->email);
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_super_admin_can_send_one_time_login_email_with_valid_temporary_password(): void
    {
        Mail::fake();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $contact = Contact::create([
            'side' => 'external',
            'name' => 'Taylor Morgan',
            'email' => 'taylor.morgan@example.com',
            'affiliation_type' => 'Company / Corporation',
            'role' => 'Industry Mentor',
            'verification_status' => 'Verified Contact',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
        ]);
        User::factory()->create([
            'name' => 'Taylor Morgan',
            'email' => 'taylor.morgan@example.com',
            'role' => 'member_incubatee',
        ]);

        $this->actingAs($superAdmin)
            ->post(route('contacts.credentials.send', $contact))
            ->assertRedirect(route('contacts'));

        Mail::assertQueued(UserCredentialInviteMail::class, function (UserCredentialInviteMail $mail) {
            $this->assertSame('taylor.morgan@example.com', $mail->email);
            $this->assertMatchesRegularExpression('/^\d{8}$/', $mail->tempPassword);
            $user = User::query()->where('email', 'taylor.morgan@example.com')->sole();
            $this->assertTrue(Hash::check($mail->tempPassword, $user->password));

            return $mail->hasTo('taylor.morgan@example.com');
        });
    }

    public function test_regular_members_and_standard_admins_denied_access_to_credential_editing_routes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);
        $contact = Contact::create([
            'side' => 'external',
            'name' => 'Dana Ellis',
            'email' => 'dana.ellis@example.com',
            'affiliation_type' => 'Company / Corporation',
            'role' => 'Industry Mentor',
            'verification_status' => 'Verified Contact',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
        ]);
        User::factory()->create([
            'name' => 'Dana Ellis',
            'email' => 'dana.ellis@example.com',
            'role' => 'member_incubatee',
        ]);

        $this->actingAs($admin)
            ->patch(route('contacts.credentials.update', $contact), ['email' => 'x@example.com'])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('contacts.credentials.send', $contact))
            ->assertForbidden();
    }
}
