<?php

namespace Tests\Feature;

use App\Enums\Forms\ApprovalStatus;
use App\Models\FormSubmission;
use App\Models\Startup;
use App\Models\TechnicalNeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_form_submissions_as_csv(): void
    {
        Startup::ensureConfigured();
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => 'AgriSense Labs',
            'track' => 'internal',
            'data' => ['founder' => 'Maria Santos', 'scores' => ['innovation' => '10/10']],
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $response = $this->actingAs($user)->get(route('exports.form-submissions'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Form Name', $content);
        $this->assertStringContainsString('Startup', $content);
        $this->assertStringContainsString('Signer Name', $content);
    }

    public function test_non_admin_cannot_export_form_submissions(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        $this->actingAs($user)
            ->get(route('exports.form-submissions'))
            ->assertStatus(403);
    }

    public function test_admin_can_export_technical_needs_as_csv(): void
    {
        Storage::fake('local');
        Startup::ensureConfigured();
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $startup = Startup::query()->firstOrFail();
        $path = UploadedFile::fake()->create('approval.pdf', 10, 'application/pdf')->store('technical-needs/'.$startup->id, 'local');
        TechnicalNeed::query()->create([
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
            'approval_document_path' => $path,
            'approval_document_original_name' => 'approval.pdf',
        ]);

        $response = $this->actingAs($user)->get(route('exports.technical-needs'));

        $response->assertStatus(200);
        $content = $response->streamedContent();
        $this->assertStringContainsString('Requirement', $content);
        $this->assertStringContainsString('Startup', $content);
        $this->assertStringContainsString('Status', $content);
        $this->assertStringContainsString('Lab Equipment', $content);
    }

    public function test_non_admin_cannot_export_technical_needs(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        $this->actingAs($user)
            ->get(route('exports.technical-needs'))
            ->assertStatus(403);
    }
}
