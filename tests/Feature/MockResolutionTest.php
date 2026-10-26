<?php

namespace Tests\Feature;

use App\Enums\Forms\ApprovalStatus;
use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\FormSubmission;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class MockResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_real_metrics_and_recent_activity(): void
    {
        Startup::ensureConfigured();
        Startup::query()->firstOrFail()->delete();

        $this->contact(['verification_status' => 'Verified Contact']);
        $this->contact(['verification_status' => 'Pending Review', 'email' => 'pending@example.test']);

        ContactRegistrationApplication::query()->create([
            'name' => 'Pending Contact',
            'email' => 'pending-contact@example.test',
            'stream' => 'external',
            'application_data' => [],
        ]);

        DB::table('intake_submissions')->insert([
            'type' => 'startup',
            'name' => 'Pending Startup',
            'email' => 'pending-startup@example.test',
            'track' => 'internal',
            'data' => json_encode([], JSON_THROW_ON_ERROR),
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ActivityLog::query()->create([
            'action' => 'created',
            'description' => 'Fresh dashboard activity',
        ]);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Fresh dashboard activity')
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['activeStartups']['all'] === 7
                && $metrics['verifiedContacts']['all'] === 1
                && $metrics['pendingIntakeApplications']['all'] === 2
                && $metrics['recentActivity']->count() === 1);
    }

    public function test_admin_can_transition_submission_approval_status_and_it_is_logged(): void
    {
        $admin = $this->admin();
        Startup::ensureConfigured();
        $submission = $this->submission($admin);

        foreach ([ApprovalStatus::InReview, ApprovalStatus::Approved, ApprovalStatus::Rejected] as $status) {
            $this->actingAs($admin)
                ->patch(route('form-submissions.status', $submission), ['status' => $status->value])
                ->assertRedirect(route('files', [
                    'startup' => Str::slug($submission->startup_name),
                    'track' => $submission->track,
                ]));

            $this->assertDatabaseHas('form_submissions', [
                'id' => $submission->id,
                'approval_status' => $status->value,
            ]);
        }

        $this->assertSame(3, ActivityLog::query()
            ->where('action', 'status_updated')
            ->where('subject_type', FormSubmission::class)
            ->where('subject_id', $submission->id)
            ->count());
    }

    public function test_member_cannot_transition_submission_approval_status(): void
    {
        Startup::ensureConfigured();
        $member = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);
        $submission = $this->submission($member);

        $this->actingAs($member)
            ->patch(route('form-submissions.status', $submission), ['status' => ApprovalStatus::Approved->value])
            ->assertForbidden();

        $this->assertSame(ApprovalStatus::Submitted, $submission->refresh()->approval_status);
    }

    public function test_public_startup_application_is_saved_and_emails_the_applicant(): void
    {
        Mail::fake();

        $this->post(route('startup-form.store'), [
            'stream' => 'academic',
            'name' => 'AquaFarm',
            'founder' => 'Ana Cruz',
            'email' => 'ana@example.test',
            'sector' => 'Agritech',
            'pitch' => 'Solar-powered irrigation for small farms.',
            'dept' => 'College of Engineering',
            'ip' => 'No',
        ])->assertRedirect(route('startup-form'));

        $this->assertDatabaseHas('intake_submissions', [
            'type' => 'startup',
            'name' => 'AquaFarm',
            'email' => 'ana@example.test',
            'track' => 'internal',
            'status' => 'Pending',
        ]);

        Mail::assertQueued('App\\Mail\\IntakeSubmissionReceived', fn ($mail): bool => $mail->hasTo('ana@example.test'));
    }

    public function test_public_alumni_update_is_saved_and_emails_the_alumnus(): void
    {
        Mail::fake();

        $this->post(route('alumni-form.store'), [
            'stream' => 'industry',
            'name' => 'Carlo Tan',
            'startup' => 'LedgerLoop',
            'email' => 'carlo@example.test',
            'gradYear' => 2024,
            'status' => 'Operating',
            'employees' => 12,
            'funding' => 'Seed',
            'revenue' => '$250k – $1M',
            'licensed' => 'No',
            'notes' => 'Expanded to two new regions.',
            'mentor' => '1',
        ])->assertRedirect(route('alumni-form'));

        $this->assertDatabaseHas('intake_submissions', [
            'type' => 'alumni',
            'name' => 'Carlo Tan',
            'email' => 'carlo@example.test',
            'track' => 'external',
            'status' => 'Pending',
        ]);

        Mail::assertQueued('App\\Mail\\IntakeSubmissionReceived', fn ($mail): bool => $mail->hasTo('carlo@example.test'));
    }

    public function test_public_intake_rejects_invalid_fields(): void
    {
        $this->from(route('startup-form'))
            ->post(route('startup-form.store'), [
                'stream' => 'academic',
                'name' => '',
                'founder' => 'Ana Cruz',
                'email' => 'not-an-email',
            ])
            ->assertRedirect(route('startup-form'))
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertDatabaseCount('intake_submissions', 0);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function submission(User $user): FormSubmission
    {
        return FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup Profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => [],
            'approval_status' => ApprovalStatus::Submitted,
        ]);
    }

    private function contact(array $attributes = []): Contact
    {
        return Contact::query()->create([
            'side' => 'internal',
            'name' => 'Verified Contact',
            'email' => 'verified@example.test',
            'affiliation_type' => 'University department / office',
            'role' => 'Campus Researcher',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
            ...$attributes,
        ]);
    }
}
