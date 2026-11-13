<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserArchiveLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $name, ?string $email): array
    {
        return [
            'side' => 'external',
            'name' => $name,
            'email' => $email,
            'affiliation_type' => 'Company',
            'role' => 'Partner',
            'organization' => 'Archive Coverage Co',
            'domains' => ['Information Technology'],
            'technical_skills' => [],
            'nda_status' => 'Pending',
        ];
    }

    public function test_creating_a_contact_provisions_a_member_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('contacts.store'), $this->payload('Auto Provisioned', 'auto.provisioned@example.com'))
            ->assertRedirect(route('contacts'));

        $contact = Contact::query()->where('email', 'auto.provisioned@example.com')->sole();
        $user = User::query()->where('email', 'auto.provisioned@example.com')->sole();

        $this->assertSame(User::ROLE_MEMBER_INCUBATEE, $user->role);
        $this->assertSame($contact->id, $user->contact_id);
    }

    public function test_updating_a_contact_without_an_account_provisions_one(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $contact = Contact::query()->create([
            ...$this->payload('Late Email', null),
            'verification_status' => 'Pending Verification',
            'incubatees' => [],
            'activity_log' => [],
        ]);

        $this->actingAs($admin)
            ->put(route('contacts.update', $contact), $this->payload('Late Email', 'late.email@example.com'))
            ->assertRedirect(route('contacts'));

        $user = User::query()->where('email', 'late.email@example.com')->sole();
        $this->assertSame(User::ROLE_MEMBER_INCUBATEE, $user->role);
        $this->assertSame($contact->id, $user->contact_id);
    }

    public function test_archiving_and_restoring_a_contact_syncs_its_linked_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)
            ->post(route('contacts.store'), $this->payload('Synced Contact', 'synced.contact@example.com'));
        $contact = Contact::query()->where('email', 'synced.contact@example.com')->sole();
        $member = User::query()->where('contact_id', $contact->id)->sole();

        $this->actingAs($admin)
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('contacts'));

        $this->assertSoftDeleted('users', ['id' => $member->id]);

        $this->actingAs($admin)
            ->post(route('archives.contacts.restore', $contact->id))
            ->assertRedirect(route('archives.index'));

        $this->assertNotSoftDeleted('users', ['id' => $member->id]);
    }

    public function test_archiving_and_restoring_a_user_syncs_its_linked_contact(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)
            ->post(route('contacts.store'), $this->payload('Synced Member', 'synced.member@example.com'));
        $contact = Contact::query()->where('email', 'synced.member@example.com')->sole();
        $member = User::query()->where('contact_id', $contact->id)->sole();

        $this->actingAs($admin)
            ->post(route('team.archive', $member))
            ->assertRedirect(route('team'));

        $this->assertSoftDeleted('users', ['id' => $member->id]);
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);

        $this->actingAs($admin)
            ->post(route('team.restore', $member->id))
            ->assertRedirect(route('team', ['tab' => 'archived']));

        $this->assertNotSoftDeleted('users', ['id' => $member->id]);
        $this->assertNotSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_a_user_cannot_archive_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('team.archive', $admin))
            ->assertStatus(422);

        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
    }

    public function test_the_last_super_admin_cannot_be_archived(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)
            ->post(route('team.archive', $superAdmin))
            ->assertStatus(422);

        $this->assertNotSoftDeleted('users', ['id' => $superAdmin->id]);
    }

    public function test_team_page_shows_active_and_archived_tabs(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $archived = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE, 'name' => 'Archived Person']);
        $archived->delete();

        $this->actingAs($admin)
            ->get(route('team'))
            ->assertOk()
            ->assertSee('Archived (1)')
            ->assertDontSee('Archived Person');

        $this->actingAs($admin)
            ->get(route('team', ['tab' => 'archived']))
            ->assertOk()
            ->assertSee('Archived Person')
            ->assertSee('Restore');
    }
}
