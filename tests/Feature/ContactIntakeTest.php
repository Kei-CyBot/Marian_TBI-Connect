<?php

namespace Tests\Feature;

use App\Mail\AccountProvisionedMail;
use App\Mail\ContactRegistrationNotificationMail;
use App\Mail\ContactRegistrationReceiptMail;
use App\Mail\IntakeSubmissionReceiptMail;
use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\IntakeSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_startup_intake_receipt_renders_all_submission_fields(): void
    {
        $submission = new IntakeSubmission([
            'type' => 'startup',
            'name' => 'Avery Founder',
            'email' => 'avery@example.test',
        ]);
        $submission->id = 42;

        $rendered = (new IntakeSubmissionReceiptMail($submission))->render();

        $this->assertStringContainsString('Avery Founder', $rendered);
        $this->assertStringContainsString('avery@example.test', $rendered);
        $this->assertStringContainsString($submission->trackingId(), $rendered);
    }

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
        Mail::fake();
        $contactsBefore = Contact::count();
        $applicationsBefore = ContactRegistrationApplication::count();

        $response = $this->post(route('contact-registration.store'), [
            'stream' => 'external',
            'name' => 'Jordan Kim',
            'email' => 'jordan.kim@example.com',
            'domain_expertise' => ['Health / Life Sciences'],
            'technical_skills' => ['Data Analytics / Data Science'],
            'tools_methods' => ['Python', 'Laravel'],
        ]);

        $response->assertRedirect(route('contact-registration'));

        $this->assertSame($applicationsBefore + 1, ContactRegistrationApplication::count());
        $this->assertSame($contactsBefore, Contact::count());

        $application = ContactRegistrationApplication::query()->where('email', 'jordan.kim@example.com')->sole();
        $this->assertSame('Pending', $application->status);
        $this->assertIsArray($application->domain_expertise);
        $this->assertIsArray($application->technical_skills);
        $this->assertIsArray($application->tools_methods);
    }

    public function test_submitting_with_uic_email_assigns_uic_internal_affiliation_and_queues_receipt(): void
    {
        Mail::fake();
        config(['mail.admin_address' => 'admin@example.test']);

        $this->post(route('contact-registration.store'), [
            'name' => 'Test UIC',
            'email' => 'test@uic.edu.ph',
        ])->assertRedirect(route('contact-registration'));

        $application = ContactRegistrationApplication::query()->where('email', 'test@uic.edu.ph')->sole();
        $this->assertSame('UIC Internal / Institutional', $application->application_data['affiliationType'] ?? $application->application_data['affiliation_type'] ?? null);
        $this->assertSame('internal', $application->stream);

        Mail::assertQueued(ContactRegistrationReceiptMail::class, fn (ContactRegistrationReceiptMail $mail): bool => $mail->hasTo('test@uic.edu.ph'));
        Mail::assertQueued(ContactRegistrationNotificationMail::class, fn (ContactRegistrationNotificationMail $mail): bool => $mail->hasTo('admin@example.test'));
    }

    public function test_submitting_with_gmail_email_assigns_external_affiliation_and_queues_receipt(): void
    {
        Mail::fake();
        config(['mail.admin_address' => 'admin@example.test']);

        $this->post(route('contact-registration.store'), [
            'name' => 'Test Gmail',
            'email' => 'test@gmail.com',
        ])->assertRedirect(route('contact-registration'));

        $application = ContactRegistrationApplication::query()->where('email', 'test@gmail.com')->sole();
        $this->assertSame('External / General', $application->application_data['affiliationType'] ?? $application->application_data['affiliation_type'] ?? null);
        $this->assertSame('external', $application->stream);

        Mail::assertQueued(ContactRegistrationReceiptMail::class, fn (ContactRegistrationReceiptMail $mail): bool => $mail->hasTo('test@gmail.com'));
    }

    public function test_verify_converts_application_to_a_contact_record(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $application = $this->application();

        $this->actingAs($admin)
            ->post(route('contact-intake.approve', $application))
            ->assertRedirect(route('contact-intake'));

        $this->assertDatabaseHas('contacts', [
            'email' => $application->email,
            'name' => $application->name,
            'verification_status' => 'Verified Contact',
        ]);

        $application->refresh();
        $this->assertSame('Approved', $application->status);
        $this->assertSame($admin->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);
        Mail::assertQueued(AccountProvisionedMail::class);
    }

    public function test_approve_and_provision_creates_contact_user_and_queues_account_mail(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $application = $this->application(['email' => 'provision@example.com']);
        $contactsBefore = Contact::count();
        $usersBefore = User::count();

        $pending = ContactRegistrationApplication::where('status', 'Pending')->get();
        $this->assertTrue($pending->contains(fn ($app) => $app->email === $application->email));

        $this->actingAs($admin)
            ->post(route('contact-intake.provision', $application))
            ->assertRedirect(route('contact-intake'));

        $this->assertSame($contactsBefore + 1, Contact::count());
        $this->assertSame($usersBefore + 1, User::count());
        Mail::assertQueued(AccountProvisionedMail::class, function (AccountProvisionedMail $mail): bool {
            $user = User::query()->where('email', 'provision@example.com')->sole();
            $this->assertTrue(Hash::check($mail->temporaryPassword, $user->password));

            return $mail->email === 'provision@example.com'
                && $mail->loginUrl === route('login')
                && $mail->hasTo('provision@example.com');
        });

        $this->assertDatabaseHas('contacts', [
            'email' => 'provision@example.com',
            'verification_status' => 'Verified Contact',
            'name' => $application->name,
        ]);

        $application->refresh();
        $this->assertSame('Approved', $application->status);
        $this->assertSame($admin->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);
    }

    public function test_admin_approval_generates_hashed_temporary_password_and_queues_account_mail(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $application = $this->application(['email' => 'provisioned@example.com']);

        $this->actingAs($admin)
            ->post(route('contact-intake.provision', $application))
            ->assertRedirect(route('contact-intake'));

        $user = User::query()->where('email', 'provisioned@example.com')->sole();
        $this->assertDatabaseHas('contacts', ['email' => 'provisioned@example.com']);
        Mail::assertQueued(AccountProvisionedMail::class, function (AccountProvisionedMail $mail) use ($user): bool {
            $this->assertSame('provisioned@example.com', $mail->email);
            $this->assertSame(route('login'), $mail->loginUrl);
            $this->assertTrue(Hash::check($mail->temporaryPassword, $user->password));
            $rendered = $mail->render();
            $this->assertStringContainsString($mail->email, $rendered);
            $this->assertStringContainsString($mail->temporaryPassword, $rendered);
            $this->assertStringContainsString($mail->loginUrl, $rendered);

            return $mail->hasTo('provisioned@example.com');
        });
    }

    public function test_approval_provisions_member_account_and_sends_login_instructions(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $application = $this->application(['email' => 'loginable@example.com']);

        $this->actingAs($admin)->post(route('contact-intake.provision', $application));

        $user = User::query()->where('email', 'loginable@example.com')->sole();
        $this->assertSame(User::ROLE_MEMBER_INCUBATEE, $user->role);
        $this->assertDatabaseHas('contacts', ['email' => 'loginable@example.com', 'name' => $application->name]);
        Mail::assertQueued(AccountProvisionedMail::class);
    }
}
