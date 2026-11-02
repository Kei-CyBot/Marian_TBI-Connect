<?php

namespace Tests\Feature;

use App\Enums\Forms\ApprovalStatus;
use App\Models\FormSubmission;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StartupFolderAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_manager_can_restrict_app_access_to_selected_team_members(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        Startup::ensureConfigured();
        $startup = Startup::query()->findOrFail(1);

        $this->actingAs($manager)
            ->post(route('startup-folders.access.update', $startup), [
                'restricted' => '1',
                'user_ids' => [$member->id],
            ])
            ->assertRedirect(route('files', ['startup' => 'agrisense-labs', 'track' => 'internal']));

        $this->assertDatabaseHas('startup_folder_access', [
            'startup_id' => $startup->id,
            'user_id' => $member->id,
            'granted_by' => $manager->id,
        ]);

        $memberResponse = $this->actingAs($member)->get(route('files'));
        $memberResponse->assertOk();
        $this->assertContains('agrisense-labs', array_column($memberResponse->viewData('startupFolders'), 'slug'));

        $outsiderResponse = $this->actingAs($outsider)->get(route('files'));
        $outsiderResponse->assertOk();
        $this->assertNotContains('agrisense-labs', array_column($outsiderResponse->viewData('startupFolders'), 'slug'));

        $this->actingAs($outsider)
            ->post(route('startup-folders.access.update', $startup), [
                'restricted' => '1',
                'user_ids' => [$outsider->id],
            ])
            ->assertForbidden();
    }

    public function test_member_files_are_limited_to_owned_submissions_and_their_startup(): void
    {
        Storage::fake('local');
        $member = User::factory()->create([
            'role' => User::ROLE_MEMBER_INCUBATEE,
            'email' => 'founder@example.test',
        ]);
        $otherUser = User::factory()->create();
        $ownStartup = Startup::query()->create([
            'name' => 'Founders Startup',
            'founder' => 'Founder',
            'contact_email' => $member->email,
            'track' => 'external',
            'status' => 'Applicant',
            'cohort_id' => 1,
        ]);
        Startup::query()->create([
            'name' => 'Other Startup',
            'founder' => 'Other Founder',
            'contact_email' => 'other@example.test',
            'track' => 'external',
            'status' => 'Applicant',
            'cohort_id' => 1,
        ]);

        $ownStartupSubmission = FormSubmission::query()->create([
            'user_id' => $otherUser->id,
            'form_id' => 1,
            'form_name' => 'Founders Form',
            'startup_name' => $ownStartup->name,
            'track' => 'external',
            'data' => [],
            'approval_status' => ApprovalStatus::Submitted,
        ]);
        $ownUserSubmission = FormSubmission::query()->create([
            'user_id' => $member->id,
            'form_id' => 1,
            'form_name' => 'Personal Form',
            'startup_name' => 'Unlinked Startup',
            'track' => 'external',
            'data' => [],
            'approval_status' => ApprovalStatus::Submitted,
        ]);
        FormSubmission::query()->create([
            'user_id' => $otherUser->id,
            'form_id' => 1,
            'form_name' => 'Other Form',
            'startup_name' => 'Other Startup',
            'track' => 'external',
            'data' => [],
            'approval_status' => ApprovalStatus::Submitted,
        ]);

        $response = $this->actingAs($member)->get(route('files'))->assertOk();
        $this->assertEqualsCanonicalizing(
            [$ownStartupSubmission->id, $ownUserSubmission->id],
            array_column($response->viewData('savedSubmissions'), 'submissionId'),
        );
        $this->assertContains('founders-startup', array_column($response->viewData('startupFolders'), 'slug'));
        $this->assertNotContains('other-startup', array_column($response->viewData('startupFolders'), 'slug'));
    }
}
