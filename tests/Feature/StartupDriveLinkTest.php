<?php

namespace Tests\Feature;

use App\Models\StartupDriveLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartupDriveLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_add_a_google_drive_link_for_a_configured_startup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('startup-drive-links.store'), [
                'startup_id' => '1',
                'label' => 'Shared project folder',
                'url' => 'https://drive.google.com/drive/folders/example',
            ])
            ->assertRedirect(route('incubatees'))
            ->assertSessionHas('drive_link_status', 'Google Drive link added to AgriSense Labs.');

        $this->assertDatabaseHas('startup_drive_links', [
            'startup_id' => '1',
            'startup_name' => 'AgriSense Labs',
            'label' => 'Shared project folder',
            'url' => 'https://drive.google.com/drive/folders/example',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('incubatees'))
            ->assertOk()
            ->assertSee('Shared project folder')
            ->assertSee('drive.google.com');
    }

    public function test_drive_link_rejects_non_google_hosts_and_unknown_startups(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('incubatees'))
            ->post(route('startup-drive-links.store'), [
                'startup_id' => '1',
                'label' => 'Unsafe link',
                'url' => 'https://example.com/shared',
            ])
            ->assertSessionHasErrors('url');

        $this->actingAs(User::factory()->create())
            ->from(route('incubatees'))
            ->post(route('startup-drive-links.store'), [
                'startup_id' => '999',
                'label' => 'Unknown startup',
                'url' => 'https://drive.google.com/drive/folders/example',
            ])
            ->assertSessionHasErrors('startup_id');

        $this->assertDatabaseCount('startup_drive_links', 0);
    }

    public function test_authenticated_user_can_remove_a_saved_drive_link(): void
    {
        $user = User::factory()->create();
        $link = StartupDriveLink::query()->create([
            'startup_id' => '1',
            'startup_name' => 'AgriSense Labs',
            'label' => 'Shared project folder',
            'url' => 'https://drive.google.com/drive/folders/example',
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('startup-drive-links.destroy', $link))
            ->assertRedirect(route('incubatees'))
            ->assertSessionHas('drive_link_status');

        $this->assertDatabaseMissing('startup_drive_links', ['id' => $link->id]);
    }

    public function test_guest_cannot_add_a_drive_link(): void
    {
        $this->post(route('startup-drive-links.store'), [])
            ->assertRedirect(route('login'));
    }
}
