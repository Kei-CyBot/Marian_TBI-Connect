<?php

namespace Tests\Feature;

use App\Enums\Forms\ApprovalStatus;
use App\Models\ActivityLog;
use App\Models\FormSubmission;
use App\Models\Startup;
use App\Models\TechnicalNeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_activity_log(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        ActivityLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'created',
            'subject_type' => FormSubmission::class,
            'subject_id' => 1,
            'description' => 'John Doe created submission Form 4 for Startup X',
        ]);

        $this->actingAs($admin)
            ->get(route('activity-logs.index'))
            ->assertOk()
            ->assertSee('Activity Log')
            ->assertSee('created submission Form 4 for Startup X');
    }

    public function test_non_admin_cannot_view_activity_log(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        $this->actingAs($user)
            ->get(route('activity-logs.index'))
            ->assertStatus(403);
    }

    public function test_form_submission_creation_is_logged(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($user)->post(route('form-submissions.store'), [
            'form_id' => 2,
            'startup' => 'AgriSense Labs',
            'track' => 'internal',
            'signer_name' => 'Maria Santos',
            'data' => json_encode(['founder' => 'Maria Santos'], JSON_THROW_ON_ERROR),
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created',
            'subject_type' => FormSubmission::class,
        ]);
    }

    public function test_form_submission_deletion_is_logged(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $submission = FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['business_name' => 'AgriSense Labs'],
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $this->actingAs($user)
            ->delete(route('form-submissions.destroy', $submission))
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'deleted',
            'subject_type' => FormSubmission::class,
        ]);
    }

    public function test_technical_need_creation_and_deletion_are_logged(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        Startup::ensureConfigured();
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $startup = Startup::query()->firstOrFail();

        $response = $this->actingAs($user)->post(route('technical-needs.store'), [
            'startup_id' => $startup->id,
            'requirement' => 'Lab Equipment',
            'description' => 'Need microscopes',
            'subscription_price' => 15000,
            'months_needed' => 12,
            'responsible_team_member' => 'Dr. Lee',
            'date_entry' => '2026-01-01',
            'date_needed' => '2026-06-01',
            'resource_tool' => 'Microscope',
            'prospect_resource_person' => 'Supplier X',
            'status' => 'Ongoing',
            'priority' => 'High',
            'remarks' => 'Urgent',
            'signed_approval' => \Illuminate\Http\UploadedFile::fake()->create('approval.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created',
            'subject_type' => TechnicalNeed::class,
        ]);

        $need = TechnicalNeed::query()->firstOrFail();
        $this->actingAs($user)
            ->delete(route('technical-needs.destroy', $need))
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'deleted',
            'subject_type' => TechnicalNeed::class,
        ]);
    }
}
