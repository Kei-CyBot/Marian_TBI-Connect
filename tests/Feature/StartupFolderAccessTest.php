<?php

namespace Tests\Feature;

use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
