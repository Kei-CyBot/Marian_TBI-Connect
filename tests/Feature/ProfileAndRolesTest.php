<?php

namespace Tests\Feature;

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
            ->assertSee('Sign in with Google');
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

    public function test_user_can_update_profile_and_password_with_current_password(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.test',
        ]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated@example.test',
                'current_password' => 'password',
                'new_password' => 'updated-password',
                'new_password_confirmation' => 'updated-password',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('profile_status');

        $this->assertSame('Updated Name', $user->refresh()->name);
        $this->assertSame('updated@example.test', $user->email);
        $this->assertTrue(password_verify('updated-password', $user->password));
    }
}
