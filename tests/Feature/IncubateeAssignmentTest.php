<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncubateeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function startup(array $attributes = []): Startup
    {
        return Startup::query()->create(array_merge([
            'name' => 'Assignment Startup',
            'founder' => 'Founder Person',
            'contact_email' => 'founder@example.test',
            'track' => 'internal',
            'status' => 'Incubatee',
            'cohort_id' => 1,
        ], $attributes));
    }

    public function test_restricted_startup_without_a_manager_still_renders_for_a_super_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->startup([
            'name' => 'AgriSense Labs',
            'folder_access_restricted' => true,
            'folder_access_manager_id' => null,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('incubatees'))
            ->assertOk()
            ->assertSee('AgriSense Labs');
    }

    public function test_admin_can_assign_and_unassign_a_member_to_an_incubatee(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create([
            'role' => User::ROLE_MEMBER_INCUBATEE,
            'name' => 'Assigned Mentor',
        ]);
        $startup = $this->startup();

        $this->actingAs($admin)
            ->post(route('incubatees.assignments.store', $startup), ['user_id' => $member->id])
            ->assertRedirect(route('incubatees'));

        $this->assertDatabaseHas('startup_folder_access', [
            'startup_id' => $startup->id,
            'user_id' => $member->id,
            'granted_by' => $admin->id,
        ]);

        $this->actingAs($member)
            ->get(route('incubatees'))
            ->assertOk()
            ->assertViewHas('startups', fn (array $startups): bool => in_array('Assignment Startup', array_column($startups, 'name'), true));

        $this->actingAs($admin)
            ->delete(route('incubatees.assignments.destroy', [$startup, $member]))
            ->assertRedirect(route('incubatees'));

        $this->assertDatabaseMissing('startup_folder_access', [
            'startup_id' => $startup->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($member)
            ->get(route('incubatees'))
            ->assertOk()
            ->assertViewHas('startups', fn (array $startups): bool => ! in_array('Assignment Startup', array_column($startups, 'name'), true));
    }

    public function test_incubatees_page_exposes_the_assignment_controls(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->startup();

        $this->actingAs($admin)
            ->get(route('incubatees'))
            ->assertOk()
            ->assertSee('Assignments')
            ->assertSee('Assign member');
    }

    public function test_contact_linked_to_the_current_user_cannot_be_archived(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $contact = $this->contact('Own Contact', 'own.contact@example.test');
        $admin->forceFill(['contact_id' => $contact->id])->save();

        $this->actingAs($admin)
            ->from(route('contacts'))
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('contacts'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('contacts', ['id' => $contact->id]);
    }

    public function test_contact_linked_to_the_last_super_admin_cannot_be_archived(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $contact = $this->contact('Super Contact', 'super.contact@example.test');
        $superAdmin->forceFill(['contact_id' => $contact->id])->save();

        $this->actingAs($admin)
            ->from(route('contacts'))
            ->delete(route('contacts.destroy', $contact))
            ->assertRedirect(route('contacts'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('contacts', ['id' => $contact->id]);
        $this->assertNotSoftDeleted('users', ['id' => $superAdmin->id]);
    }

    private function contact(string $name, string $email): Contact
    {
        return Contact::query()->create([
            'side' => 'external',
            'name' => $name,
            'email' => $email,
            'affiliation_type' => 'Company',
            'role' => 'Partner',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
        ]);
    }
}
