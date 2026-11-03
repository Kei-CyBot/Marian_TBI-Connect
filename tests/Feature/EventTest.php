<?php

namespace Tests\Feature;

use App\Mail\EventCancelledMail;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['mail.default' => 'array']);

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->event = $this->event();
    }

    public function test_admin_can_mark_an_event_as_completed(): void
    {
        $this->actingAs($this->admin)
            ->post(route('events.complete', $this->event), [
                'actual_attendees_count' => 42,
                'post_event_notes' => 'Strong turnout and useful follow-ups.',
                'post_event_document' => UploadedFile::fake()->create('post-event.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('events.show', $this->event))
            ->assertSessionHas('event_status', 'Event marked as completed.');

        $event = $this->event->fresh();

        $this->assertSame('completed', $event->status);
        $this->assertNotNull($event->completed_at);
        $this->assertNotNull($event->archived_at);
        $this->assertSame(42, $event->actual_attendees_count);
        $this->assertSame('Strong turnout and useful follow-ups.', $event->post_event_notes);
        $this->assertSame('post-event.pdf', $event->post_event_document_original_name);
        Storage::disk('local')->assertExists($event->post_event_document_path);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'event_completed',
            'subject_type' => Event::class,
            'subject_id' => $event->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Completed')
            ->assertSee('42 actual attendee(s)');
    }

    public function test_admin_can_cancel_an_event_and_notify_invitees(): void
    {
        Mail::fake();

        $invitation = $this->invitation();
        $declined = EventInvitation::query()->create([
            'event_id' => $this->event->id,
            'email' => 'declined@example.test',
            'name' => 'Declined Guest',
            'token' => Str::random(40),
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('events.cancel', $this->event), [
                'cancellation_reason' => 'Venue unavailable.',
                'send_cancellation_email' => '1',
            ])
            ->assertRedirect(route('events.show', $this->event))
            ->assertSessionHas('event_status', 'Event cancelled.');

        $event = $this->event->fresh();

        $this->assertSame('cancelled', $event->status);
        $this->assertNotNull($event->cancelled_at);
        $this->assertNotNull($event->archived_at);
        $this->assertSame('Venue unavailable.', $event->cancellation_reason);
        $this->assertSame('pending', $invitation->fresh()->status);

        Mail::assertSent(EventCancelledMail::class, function (EventCancelledMail $mail) use ($invitation): bool {
            return $mail->event->is($this->event)
                && $mail->invitation->is($invitation)
                && $mail->hasTo($invitation->email);
        });

        Mail::assertNotSent(EventCancelledMail::class, fn (EventCancelledMail $mail): bool => $mail->hasTo($declined->email));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'event_cancelled',
            'subject_type' => Event::class,
            'subject_id' => $event->id,
        ]);
    }

    public function test_cancellation_email_is_only_sent_when_requested(): void
    {
        Mail::fake();
        $this->invitation();

        $this->actingAs($this->admin)
            ->post(route('events.cancel', $this->event), [
                'cancellation_reason' => 'Schedule conflict.',
            ])
            ->assertRedirect();

        Mail::assertNothingSent();
    }

    public function test_cancelled_event_rsvp_link_returns_cancelled_state_and_does_not_record_a_response(): void
    {
        $invitation = $this->invitation();

        $this->actingAs($this->admin)
            ->post(route('events.cancel', $this->event), [
                'cancellation_reason' => 'Venue unavailable.',
            ])
            ->assertRedirect();

        $this->get(route('events.invitations.respond', [
            'token' => $invitation->token,
            'status' => 'attending',
        ]))
            ->assertOk()
            ->assertSee('Event cancelled')
            ->assertSee('This event was cancelled.')
            ->assertDontSee('Your response has been recorded');

        $invitation->refresh();
        $this->assertSame('pending', $invitation->status);
        $this->assertNull($invitation->responded_at);
    }

    public function test_admin_can_archive_and_restore_an_event(): void
    {
        $this->actingAs($this->admin)
            ->post(route('events.archive', $this->event))
            ->assertRedirect(route('archives.index', ['tab' => 'events']))
            ->assertSessionHas('archive_status', 'Event archived.');

        $event = $this->event->fresh();
        $this->assertNotNull($event->archived_at);
        $this->assertSame('scheduled', $event->status);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'event_archived',
            'subject_type' => Event::class,
            'subject_id' => $event->id,
        ]);

        $event->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'archived_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('events.restore', $event))
            ->assertRedirect(route('archives.index', ['tab' => 'events']))
            ->assertSessionHas('archive_status', 'Event restored.');

        $event->refresh();
        $this->assertNull($event->archived_at);
        $this->assertSame('scheduled', $event->status);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'event_restored',
            'subject_type' => Event::class,
            'subject_id' => $event->id,
        ]);
    }

    public function test_archives_events_tab_lists_completed_and_cancelled_events(): void
    {
        $this->invitation();
        $this->event->update([
            'status' => 'completed',
            'completed_at' => now(),
            'archived_at' => now(),
            'actual_attendees_count' => 1,
            'post_event_notes' => 'Follow-up meetings booked.',
        ]);

        $cancelled = $this->event([
            'title' => 'Cancelled Summit',
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'archived_at' => now(),
            'cancellation_reason' => 'Venue unavailable.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('archives.index', ['tab' => 'events']))
            ->assertOk()
            ->assertSee('Event Archives')
            ->assertSee($this->event->title)
            ->assertSee($cancelled->title)
            ->assertSee('Venue unavailable.')
            ->assertSee('Follow-up meetings booked.');
    }

    public function test_active_events_page_hides_archived_events(): void
    {
        $archived = $this->event([
            'title' => 'Archived Planning Session',
            'archived_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('events'))
            ->assertOk()
            ->assertSee($this->event->title)
            ->assertDontSee($archived->title);
    }

    public function test_event_status_scopes_filter_events(): void
    {
        $completed = $this->event(['title' => 'Completed Event', 'status' => 'completed']);
        $cancelled = $this->event(['title' => 'Cancelled Event', 'status' => 'cancelled']);
        $archived = $this->event(['title' => 'Archived Event', 'archived_at' => now()]);

        $this->assertTrue(Event::query()->completed()->get()->contains($completed));
        $this->assertTrue(Event::query()->cancelled()->get()->contains($cancelled));

        $archivedEvents = Event::query()->archived()->get();
        $this->assertTrue($archivedEvents->contains($completed));
        $this->assertTrue($archivedEvents->contains($cancelled));
        $this->assertTrue($archivedEvents->contains($archived));
        $this->assertFalse($archivedEvents->contains($this->event));

        $activeEvents = Event::query()->active()->get();
        $this->assertTrue($activeEvents->contains($this->event));
        $this->assertFalse($activeEvents->contains($archived));
        $this->assertFalse($activeEvents->contains($cancelled));
    }

    private function event(array $overrides = []): Event
    {
        return Event::query()->create($overrides + [
            'title' => 'Founder Fireside',
            'scope' => 'internal',
            'kind' => 'Fireside Chat',
            'starts_at' => '2026-12-01 09:30:00',
            'ends_at' => '2026-12-01 11:15:00',
            'location' => 'Innovation Hub',
            'host' => 'Marian TBI',
        ]);
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
