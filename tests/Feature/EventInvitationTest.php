<?php

namespace Tests\Feature;

use App\Mail\EventInvitationMail;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EventInvitationTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array', 'app.debug' => false]);

        $this->event = Event::query()->create([
            'title' => 'Founder Fireside',
            'scope' => 'internal',
            'kind' => 'Fireside Chat',
            'starts_at' => '2026-12-01 09:30:00',
            'ends_at' => '2026-12-01 11:15:00',
            'location' => 'Innovation Hub',
            'host' => 'Marian TBI',
        ]);

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_invitation_creation_defaults_to_pending(): void
    {
        $invitation = EventInvitation::query()->create([
            'event_id' => $this->event->id,
            'email' => 'guest@example.test',
            'token' => Str::random(40),
        ]);

        $this->assertSame('pending', $invitation->fresh()->status);
        $this->assertNull($invitation->fresh()->responded_at);
    }

    public function test_guest_can_accept_an_invitation(): void
    {
        $invitation = $this->invitation();

        $this->get(route('events.invitations.respond', [
            'token' => $invitation->token,
            'status' => 'attending',
        ]))
            ->assertOk()
            ->assertSee('Founder Fireside')
            ->assertSee('Marian TBI')
            ->assertSee('Attending');

        $invitation->refresh();
        $this->assertSame('attending', $invitation->status);
        $this->assertNotNull($invitation->responded_at);
    }

    public function test_guest_can_decline_an_invitation(): void
    {
        $invitation = $this->invitation();

        $this->get(route('events.invitations.respond', [
            'token' => $invitation->token,
            'status' => 'declined',
        ]))
            ->assertOk()
            ->assertSee('Declined');

        $invitation->refresh();
        $this->assertSame('declined', $invitation->status);
        $this->assertNotNull($invitation->responded_at);
    }

    public function test_unknown_status_is_rejected(): void
    {
        $invitation = $this->invitation();

        $this->get(route('events.invitations.respond', [
            'token' => $invitation->token,
            'status' => 'maybe',
        ]))->assertNotFound();

        $this->assertSame('pending', $invitation->fresh()->status);
    }

    public function test_admin_can_send_invitations_and_mail_is_dispatched(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('events.invitations.send', $this->event), [
                'emails' => ['guest@example.test'],
            ])
            ->assertRedirect();

        $invitation = EventInvitation::query()->firstOrFail();
        $this->assertSame($this->event->id, $invitation->event_id);
        $this->assertSame('guest@example.test', $invitation->email);
        $this->assertSame('pending', $invitation->status);
        $this->assertSame(40, strlen($invitation->token));

        Mail::assertSent(EventInvitationMail::class, function (EventInvitationMail $mail) use ($invitation): bool {
            return $mail->invitation->is($invitation)
                && $mail->hasTo('guest@example.test');
        });
    }

    public function test_invitation_email_uses_inline_absolute_rsvp_links(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('events.invitations.send', $this->event), [
                'emails' => ['guest@example.test'],
            ]);

        Mail::assertSent(EventInvitationMail::class, function (EventInvitationMail $mail): bool {
            $html = $mail->render();
            $base = url('/events/invitations/'.$mail->invitation->token.'/respond');

            return str_contains($html, 'background-color: #16a34a')
                && str_contains($html, 'background-color: #dc2626')
                && str_contains($html, $base.'/attending')
                && str_contains($html, $base.'/declined')
                && ! str_contains($html, '<button');
        });
    }

    public function test_partial_delivery_failure_reports_sent_and_failed_counts(): void
    {
        $this->mockMailDelivery(['bad@example.test']);

        $this->actingAs($this->admin)
            ->post(route('events.invitations.send', $this->event), [
                'emails' => ['good@example.test', 'bad@example.test'],
            ])
            ->assertSessionHas('email_error', 'Sent 1 invitation(s), but 1 recipient(s) failed.');

        $this->assertSame(2, EventInvitation::query()->count());
    }

    public function test_total_delivery_failure_prompts_an_smtp_check(): void
    {
        $this->mockMailDelivery(['one@example.test', 'two@example.test']);

        $this->actingAs($this->admin)
            ->post(route('events.invitations.send', $this->event), [
                'emails' => ['one@example.test', 'two@example.test'],
            ])
            ->assertSessionHas('email_error', 'Email delivery failed. Please check your SMTP settings.');
    }

    public function test_debug_mode_includes_raw_mail_error_in_flash(): void
    {
        config(['app.debug' => true]);
        $this->mockMailDelivery(['one@example.test']);

        $this->actingAs($this->admin)
            ->post(route('events.invitations.send', $this->event), [
                'emails' => ['one@example.test'],
            ])
            ->assertSessionHas('email_error', 'Email delivery failed. Please check your SMTP settings. SMTP rejected the recipient.');
    }

    public function test_members_cannot_send_invitations(): void
    {
        Mail::fake();

        $member = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        $this->actingAs($member)
            ->post(route('events.invitations.send', $this->event), [
                'emails' => ['guest@example.test'],
            ])
            ->assertForbidden();

        $this->assertSame(0, EventInvitation::query()->count());
        Mail::assertNothingSent();
    }

    private function mockMailDelivery(array $failingEmails): void
    {
        Mail::shouldReceive('to')->andReturnUsing(function (string $email) use ($failingEmails): PendingMail {
            $pending = Mockery::mock(PendingMail::class);

            if (in_array($email, $failingEmails, true)) {
                $pending->shouldReceive('send')->andThrow(new RuntimeException('SMTP rejected the recipient.'));
            } else {
                $pending->shouldReceive('send')->once();
            }

            return $pending;
        });
    }

    private function invitation(): EventInvitation
    {
        return EventInvitation::query()->create([
            'event_id' => $this->event->id,
            'email' => 'guest@example.test',
            'name' => 'Guest Speaker',
            'token' => Str::random(40),
        ]);
    }
}
