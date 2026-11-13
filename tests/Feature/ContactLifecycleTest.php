<?php

namespace Tests\Feature;

use App\Mail\AccountProvisionedMail;
use App\Mail\OneTimeLoginMail;
use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
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

    public function test_approval_creates_user_with_hashed_password_and_queues_provisioning_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $application = $this->application(['email' => 'provisioned2@example.com']);
        $usersBefore = User::count();

        $this->actingAs($admin)
            ->post(route('contact-intake.provision', $application))
            ->assertRedirect(route('contact-intake'));

        $this->assertSame($usersBefore + 1, User::count());
        $user = User::query()->where('email', 'provisioned2@example.com')->sole();
        Mail::assertQueued(AccountProvisionedMail::class, function (AccountProvisionedMail $mail) use ($user): bool {
            $this->assertSame('provisioned2@example.com', $mail->email);
            $this->assertTrue(Hash::check($mail->temporaryPassword, $user->password));

            return $mail->hasTo('provisioned2@example.com');
        });
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

    public function test_super_admin_can_send_and_consume_one_time_login_link(): void
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
        $user = User::factory()->create([
            'name' => 'Taylor Morgan',
            'email' => 'taylor.morgan@example.com',
            'role' => 'member_incubatee',
            'password' => 'keep-this-password',
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => 'existing-password-reset',
            'created_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->post(route('contacts.credentials.send', $contact))
            ->assertRedirect(route('contacts'));

        $this->assertTrue(Hash::check('keep-this-password', $user->fresh()->password));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email, 'token' => 'existing-password-reset']);
        $loginUrl = null;
        Mail::assertQueued(OneTimeLoginMail::class, function (OneTimeLoginMail $mail) use (&$loginUrl): bool {
            $loginUrl = $mail->loginUrl;
            $this->assertStringContainsString(htmlspecialchars($mail->loginUrl, ENT_QUOTES), $mail->render());

            return $mail->hasTo('taylor.morgan@example.com');
        });

        $this->get($loginUrl)
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'Welcome to Marian TBI Connect! Please set your personal account password to complete your setup.')
            ->assertSessionHas('force_password_change', true);
        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('profile.edit'));

        $this->put(route('profile.password.update'), [
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('profile.edit'))
            ->assertSessionMissing('force_password_change');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->get(route('dashboard'))->assertOk();

        Auth::logout();
        $this->get($loginUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Login token is invalid or already used.');
        $this->assertDatabaseMissing('one_time_login_tokens', ['email' => $user->email]);
    }

    public function test_send_one_time_link_requires_a_contact_email(): void
    {
        Mail::fake();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $contact = Contact::create([
            'side' => 'external',
            'name' => 'No Email',
            'email' => null,
            'affiliation_type' => 'Company / Corporation',
            'role' => 'Industry Mentor',
            'verification_status' => 'Verified Contact',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
        ]);

        $this->actingAs($superAdmin)
            ->from(route('contacts'))
            ->post(route('contacts.credentials.send', $contact))
            ->assertRedirect(route('contacts'))
            ->assertSessionHas('error', 'Contact does not have a valid email address.');

        Mail::assertNothingQueued();
    }

    public function test_magic_login_rejects_invalid_signatures_and_tokens_with_login_flashes(): void
    {
        $email = 'magic@example.test';
        $token = 'one-time-token';
        DB::table('one_time_login_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->get(route('auth.magic-login', ['token' => 'tampered', 'email' => $email]))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'This login link has expired or is invalid.');

        $expiredUrl = URL::temporarySignedRoute(
            'auth.magic-login',
            now()->subMinute(),
            ['token' => $token, 'email' => $email],
        );
        $this->get($expiredUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'This login link has expired or is invalid.');

        $missingEmailUrl = URL::temporarySignedRoute(
            'auth.magic-login',
            now()->addMinutes(15),
            ['token' => $token],
        );
        $this->get($missingEmailUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'This login link has expired or is invalid.');

        $invalidTokenUrl = URL::temporarySignedRoute(
            'auth.magic-login',
            now()->addMinutes(15),
            ['token' => 'wrong-token', 'email' => $email],
        );
        $this->get($invalidTokenUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Login token is invalid or already used.');

        $unknownUserUrl = URL::temporarySignedRoute(
            'auth.magic-login',
            now()->addMinutes(15),
            ['token' => $token, 'email' => $email],
        );
        $this->get($unknownUserUrl)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'User account not found.');
        $this->assertDatabaseHas('one_time_login_tokens', ['email' => $email]);
    }

    public function test_credentials_update_rejects_email_owned_by_another_linked_account(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $contact = Contact::create([
            'side' => 'external',
            'name' => 'Collision Contact',
            'email' => 'collision@example.com',
            'affiliation_type' => 'Company / Corporation',
            'role' => 'Industry Mentor',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
        ]);
        $otherContact = Contact::create([
            'side' => 'external',
            'name' => 'Account Owner',
            'email' => 'owner@example.com',
            'affiliation_type' => 'Company / Corporation',
            'role' => 'Industry Mentor',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
        ]);
        $otherUser = User::factory()->create([
            'email' => 'collision@example.com',
            'role' => User::ROLE_MEMBER_INCUBATEE,
            'password' => 'keep-this-password',
            'contact_id' => $otherContact->id,
        ]);

        $this->actingAs($superAdmin)
            ->from(route('contacts'))
            ->patch(route('contacts.credentials.update', $contact), [
                'email' => 'collision@example.com',
                'password' => 'hijack-password',
            ])
            ->assertSessionHasErrors('email');

        $otherUser->refresh();
        $this->assertSame($otherContact->id, $otherUser->contact_id);
        $this->assertTrue(Hash::check('keep-this-password', $otherUser->password));
        $this->assertSame('collision@example.com', $contact->fresh()->email);
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
