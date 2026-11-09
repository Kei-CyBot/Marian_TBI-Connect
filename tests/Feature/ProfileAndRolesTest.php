<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAndRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_out_returns_to_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Welcome back');
    }

    public function test_member_can_view_workspace_but_cannot_change_records(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        $this->actingAs($member)
            ->get(route('incubatees'))
            ->assertOk();

        $this->actingAs($member)
            ->post(route('incubatees.store'), [])
            ->assertForbidden();
    }

    public function test_only_super_admin_can_change_roles(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        $this->actingAs($admin)
            ->put(route('team.roles.update', $member), ['role' => User::ROLE_ADMIN])
            ->assertForbidden();

        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdmin)
            ->put(route('team.roles.update', $superAdmin), ['role' => User::ROLE_ADMIN])
            ->assertStatus(422);

        $this->actingAs($superAdmin)
            ->put(route('team.roles.update', $member), ['role' => User::ROLE_ADMIN])
            ->assertRedirect(route('team'));

        $this->assertSame(User::ROLE_ADMIN, $member->refresh()->role);
    }

    public function test_role_changes_immediately_apply_to_team_permissions(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $member = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        $this->actingAs($member)->get(route('team'))->assertForbidden();

        $this->actingAs($superAdmin)
            ->put(route('team.roles.update', $member), ['role' => User::ROLE_ADMIN])
            ->assertRedirect(route('team'));

        $this->actingAs($member->refresh())->get(route('team'))->assertOk();
    }

    public function test_user_can_update_profile_without_a_password(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.test',
        ]);
        $originalPassword = $user->password;

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated@example.test',
                'new_password' => 'updated-password',
                'new_password_confirmation' => 'updated-password',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('profile_status')
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated Name', $user->refresh()->name);
        $this->assertSame('updated@example.test', $user->email);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_user_can_update_password_from_dedicated_password_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'new_password' => 'updated-password',
                'new_password_confirmation' => 'updated-password',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('profile_status');

        $this->assertTrue(password_verify('updated-password', $user->refresh()->password));
    }

    public function test_user_can_update_contact_details(): void
    {
        $user = User::factory()->create(['name' => 'Original Name']);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => $user->email,
                'phone' => '09171234567',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('profile_status');

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('09171234567', $user->phone);
    }

    public function test_user_can_update_extended_contact_credentials(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'member@example.test',
        ]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => 'member@example.test',
                'phone' => '09171234567',
                'affiliation' => 'University department / office',
                'organization' => 'Marian University',
                'role' => 'Student Lead',
                'domain_expertise' => ['Health / Life Sciences', 'Education'],
                'technical_skills' => ['Data Analytics / Data Science', 'Programming / Software Development'],
                'tools_languages_methods' => ['Laravel', 'Figma'],
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('profile_status');

        $contact = Contact::query()->where('email', 'member@example.test')->sole();
        $this->assertSame($contact->id, $user->refresh()->contact_id);
        $this->assertSame('Updated Name', $contact->name);
        $this->assertSame('09171234567', $contact->phone);
        $this->assertSame('University department / office', $contact->affiliation_type);
        $this->assertSame('Marian University', $contact->organization);
        $this->assertSame('Student Lead', $contact->role);
        $this->assertSame(['Health / Life Sciences', 'Education'], $contact->domains);
        $this->assertSame(['Data Analytics / Data Science', 'Programming / Software Development'], $contact->technical_skills);
        $this->assertSame('Laravel, Figma', $contact->skill_details);
    }

    public function test_selecting_other_saves_custom_contact_values(): void
    {
        $user = User::factory()->create(['email' => 'other@example.test']);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'affiliation' => 'Other',
                'affiliation_other' => 'Community makerspace',
                'role' => 'Other',
                'role_other' => 'Community Lead',
                'domain_expertise' => ['Education', 'Other'],
                'domain_expertise_other' => 'Space Tech',
                'technical_skills' => ['Other'],
                'technical_skills_other' => 'Robotics',
                'tools_languages_methods' => ['Laravel', 'Other'],
                'tools_languages_methods_other' => 'Rust, KiCad',
            ])
            ->assertRedirect(route('profile.edit'));

        $contact = Contact::query()->where('email', $user->email)->sole();
        $this->assertSame('Community makerspace', $contact->affiliation_type);
        $this->assertSame('Community Lead', $contact->role);
        $this->assertSame(['Education', 'Space Tech'], $contact->domains);
        $this->assertSame(['Robotics'], $contact->technical_skills);
        $this->assertSame('Laravel, Rust, KiCad', $contact->skill_details);
    }

    public function test_profile_edit_renders_contact_choice_options_and_preselections(): void
    {
        $user = User::factory()->create(['email' => 'choices@example.test']);

        Contact::query()->create([
            'side' => 'external',
            'name' => 'Choice Tester',
            'email' => 'choices@example.test',
            'affiliation_type' => 'Company / Corporation',
            'role' => 'Angel Investor',
            'domains' => ['Education', 'Space Tech'],
            'technical_skills' => ['Cybersecurity'],
            'skill_details' => 'Figma, KiCad',
            'incubatees' => [],
            'activity_log' => [],
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('name="affiliation"', false)
            ->assertSee('name="role"', false)
            ->assertSee('name="domain_expertise[]"', false)
            ->assertSee('name="technical_skills[]"', false)
            ->assertSee('name="tools_languages_methods[]"', false)
            ->assertSee('value="Company / Corporation" selected', false)
            ->assertSee('value="Angel Investor" selected', false)
            ->assertSee('value="Education" x-model="domains" checked', false)
            ->assertSee('value="Cybersecurity" x-model="skills" checked', false)
            ->assertSee('value="Figma" x-model="tools" checked', false)
            ->assertSee('name="domain_expertise_other" value="Space Tech"', false)
            ->assertSee('name="tools_languages_methods_other" value="KiCad"', false);
    }

    public function test_profile_update_cannot_change_the_user_role(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '09170000000',
                'role' => User::ROLE_SUPER_ADMIN,
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame(User::ROLE_MEMBER_INCUBATEE, $user->role);
        $this->assertSame('09170000000', $user->phone);
    }

    public function test_changing_password_requires_the_current_password(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'current_password' => 'wrong-password',
                'new_password' => 'updated-password',
                'new_password_confirmation' => 'updated-password',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('current_password');

        $this->assertSame($originalPassword, $user->refresh()->password);
    }
}
