<?php

namespace Tests\Feature;

use App\Mail\TeamInviteMail;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamInviteTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(array $attributes = []): TeamInvitation
    {
        return TeamInvitation::create(array_merge([
            'email' => 'invitee@example.com',
            'role' => User::ROLE_ADMIN,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'invited_by' => User::factory()->create()->id,
        ], $attributes));
    }

    public function test_admin_can_invite_a_team_member(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('team.invite'), [
                'email' => 'new.member@example.com',
                'role' => User::ROLE_ADMIN,
            ])
            ->assertRedirect(route('team'));

        $invite = TeamInvitation::query()->sole();
        $this->assertSame('new.member@example.com', $invite->email);
        $this->assertSame(User::ROLE_ADMIN, $invite->role);
        $this->assertSame(64, strlen($invite->token));
        $this->assertSame($admin->id, $invite->invited_by);
        $this->assertTrue($invite->expires_at->between(now()->addDays(6), now()->addDays(8)));

        Mail::assertQueued(TeamInviteMail::class, fn (TeamInviteMail $mail): bool => $mail->hasTo('new.member@example.com'));
    }

    public function test_team_page_lists_pending_invitations(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->invitation(['email' => 'pending@example.com']);

        $this->actingAs($admin)
            ->get(route('team'))
            ->assertOk()
            ->assertSee('pending@example.com')
            ->assertSee('Revoke');
    }

    public function test_invited_user_can_accept_and_create_an_account(): void
    {
        $invite = $this->invitation(['email' => 'accepted@example.com', 'role' => User::ROLE_ADMIN]);

        $this->get(route('invites.accept', $invite->token))
            ->assertOk()
            ->assertSee('accepted@example.com');

        $this->post(route('invites.accept.process', $invite->token), [
            'name' => 'New Teammate',
            'password' => 'supersecret123',
            'password_confirmation' => 'supersecret123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $user = User::query()->where('email', 'accepted@example.com')->sole();
        $this->assertSame('New Teammate', $user->name);
        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertTrue(Hash::check('supersecret123', $user->password));
        $this->assertNotNull($invite->refresh()->accepted_at);
    }

    public function test_unknown_or_expired_tokens_are_rejected(): void
    {
        $expired = $this->invitation([
            'email' => 'expired@example.com',
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('invites.accept', 'not-a-real-token'))->assertNotFound();
        $this->get(route('invites.accept', $expired->token))->assertNotFound();

        $this->post(route('invites.accept.process', $expired->token), [
            'name' => 'Expired User',
            'password' => 'supersecret123',
            'password_confirmation' => 'supersecret123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'expired@example.com']);
        $this->assertNull($expired->refresh()->accepted_at);
    }

    public function test_invitation_cannot_be_accepted_twice(): void
    {
        $invite = $this->invitation(['email' => 'once@example.com']);

        $this->post(route('invites.accept.process', $invite->token), [
            'name' => 'Once User',
            'password' => 'supersecret123',
            'password_confirmation' => 'supersecret123',
        ])->assertRedirect(route('dashboard'));

        $response = $this->post(route('invites.accept.process', $invite->token), [
            'name' => 'Again User',
            'password' => 'supersecret123',
            'password_confirmation' => 'supersecret123',
        ]);

        $this->assertTrue(in_array($response->status(), [404, 302], true));
    }

    public function test_admin_can_revoke_a_pending_invitation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $invite = $this->invitation();

        $this->actingAs($admin)
            ->delete(route('team.invitations.revoke', $invite))
            ->assertRedirect(route('team'));

        $this->assertDatabaseMissing('team_invitations', ['id' => $invite->id]);
    }
}
