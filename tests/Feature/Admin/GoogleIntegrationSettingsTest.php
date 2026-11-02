<?php

namespace Tests\Feature\Admin;

use App\Models\GoogleDriveConnection;
use App\Models\Setting;
use App\Models\User;
use App\Support\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleIntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.settings.google.index'))->assertRedirect(route('login'));
        $this->post(route('admin.settings.google.update-folder'), ['folder_id' => 'guest-folder'])
            ->assertRedirect(route('login'));
    }

    public function test_standard_members_are_forbidden(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        $this->actingAs($member)->get(route('admin.settings.google.index'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.settings.google.update-folder'), ['folder_id' => 'member-folder'])
            ->assertForbidden();
        $this->actingAs($member)->post(route('admin.settings.google.disconnect'))->assertForbidden();
    }

    public function test_admins_and_super_admins_can_view_the_settings_page(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.settings.google.index'))
                ->assertOk();
        }
    }

    public function test_page_shows_the_active_connection_and_configured_folder(): void
    {
        config(['services.google.folder_id' => 'configured-folder']);
        $connection = $this->createConnection();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]))
            ->get(route('admin.settings.google.index'))
            ->assertOk()
            ->assertSee('drive@example.test')
            ->assertSee('Connected')
            ->assertSee($connection->expires_at->format('M j, Y g:i A'))
            ->assertSee('configured-folder');
    }

    public function test_page_requests_action_when_no_active_connection_exists(): void
    {
        config([
            'services.google.refresh_token' => null,
            'services.google.drive_account_email' => null,
            'services.google.folder_id' => 'configured-folder',
        ]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('admin.settings.google.index'))
            ->assertOk()
            ->assertSee('Action Required')
            ->assertSee('configured-folder');
    }

    public function test_folder_setting_is_persisted_and_applied_to_the_connection(): void
    {
        $connection = $this->createConnection();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]))
            ->from(route('admin.settings.google.index'))
            ->post(route('admin.settings.google.update-folder'), ['folder_id' => 'new-folder-id'])
            ->assertRedirect(route('admin.settings.google.index'))
            ->assertSessionHas('success', 'Google Drive target folder updated successfully.');

        $this->assertDatabaseHas('settings', [
            'key' => 'google.drive_folder_id',
            'value' => 'new-folder-id',
        ]);
        $this->assertSame('new-folder-id', $connection->refresh()->root_folder_id);
        $this->assertSame('https://drive.google.com/drive/folders/new-folder-id', $connection->root_folder_url);
    }

    public function test_folder_id_is_required(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->from(route('admin.settings.google.index'))
            ->post(route('admin.settings.google.update-folder'), ['folder_id' => ''])
            ->assertSessionHasErrors('folder_id');

        $this->assertDatabaseMissing('settings', ['key' => 'google.drive_folder_id']);
    }

    public function test_super_admin_can_disconnect_the_workspace_account(): void
    {
        $this->createConnection();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]))
            ->from(route('admin.settings.google.index'))
            ->post(route('admin.settings.google.disconnect'))
            ->assertRedirect(route('admin.settings.google.index'))
            ->assertSessionHas('success', 'Google Workspace account disconnected.');

        $this->assertDatabaseCount('google_drive_connections', 0);
    }

    public function test_configured_folder_is_used_as_the_drive_root(): void
    {
        $connection = $this->createConnection();
        Setting::query()->create(['key' => 'google.drive_folder_id', 'value' => 'configured-root']);

        $createdFolders = 0;
        Http::fake(function ($request) use (&$createdFolders) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            if ($request->method() === 'GET' && $path === '/drive/v3/files') {
                return Http::response(['files' => []]);
            }

            if ($request->method() === 'POST' && $path === '/drive/v3/files') {
                $createdFolders++;

                return Http::response([
                    'id' => 'startup-folder',
                    'webViewLink' => 'https://drive.google.com/drive/folders/startup-folder',
                ]);
            }

            return Http::response(['error' => 'Unexpected request'], 500);
        });

        $folderId = app(GoogleDriveService::class)->ensureStartupFolder('Configured Startup');

        $this->assertSame('startup-folder', $folderId);
        $this->assertSame(1, $createdFolders);
        $this->assertSame('configured-root', $connection->refresh()->root_folder_id);
        $this->assertSame('https://drive.google.com/drive/folders/configured-root', $connection->root_folder_url);
    }

    private function createConnection(): GoogleDriveConnection
    {
        return GoogleDriveConnection::query()->create([
            'singleton_key' => 'marian-tbi',
            'google_email' => 'drive@example.test',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'connected_by' => User::factory()->create(['role' => User::ROLE_ADMIN])->id,
        ]);
    }
}
