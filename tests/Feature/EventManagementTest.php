<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Startup $startup;

    protected function setUp(): void
    {
        parent::setUp();

        Startup::ensureConfigured();
        $this->startup = Startup::query()->orderBy('name')->firstOrFail();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_create_form_offers_host_options_and_datetime_inputs(): void
    {
        $this->actingAs($this->admin)
            ->get(route('events.create'))
            ->assertOk()
            ->assertSee('Marian TBI')
            ->assertSee($this->startup->name)
            ->assertSee('Others')
            ->assertSee('datetime-local', false)
            ->assertDontSee('target_audience');
    }

    public function test_event_can_be_created_with_marian_tbi_as_host(): void
    {
        $this->actingAs($this->admin)
            ->post(route('events.store'), $this->eventPayload(['host' => 'Marian TBI']))
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'title' => 'Founder Workshop',
            'host' => 'Marian TBI',
        ]);
    }

    public function test_event_can_be_created_with_an_incubatee_as_host(): void
    {
        $this->actingAs($this->admin)
            ->post(route('events.store'), $this->eventPayload(['host' => $this->startup->name]))
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'title' => 'Founder Workshop',
            'host' => $this->startup->name,
        ]);
    }

    public function test_event_can_be_created_with_a_custom_host(): void
    {
        $this->actingAs($this->admin)
            ->post(route('events.store'), $this->eventPayload([
                'host' => '__other__',
                'host_other' => 'Regional Development Council',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'title' => 'Founder Workshop',
            'host' => 'Regional Development Council',
        ]);
    }

    public function test_start_and_end_datetimes_persist_and_render_cleanly(): void
    {
        $this->actingAs($this->admin)
            ->post(route('events.store'), $this->eventPayload([
                'starts_at' => '2026-12-01T09:30',
                'ends_at' => '2026-12-01T11:15',
            ]))
            ->assertRedirect();

        $event = Event::query()->firstOrFail();

        $this->assertSame('2026-12-01 09:30:00', $event->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01 11:15:00', $event->ends_at->format('Y-m-d H:i:s'));

        $this->actingAs($this->admin)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('9:30 AM - Dec 01, 2026')
            ->assertSee('11:15 AM - Dec 01, 2026')
            ->assertDontSee('target_audience');
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->actingAs($this->admin)
            ->post(route('events.store'), $this->eventPayload([
                'starts_at' => '2026-12-01T11:00',
                'ends_at' => '2026-12-01T09:00',
            ]))
            ->assertSessionHasErrors('ends_at');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_members_cannot_create_events(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        $this->actingAs($member)->get(route('events.create'))->assertForbidden();
        $this->actingAs($member)->post(route('events.store'), $this->eventPayload())->assertForbidden();
    }

    private function eventPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Founder Workshop',
            'scope' => 'internal',
            'kind' => 'Campus Hackathon',
            'starts_at' => '2026-12-01T09:00',
            'ends_at' => '2026-12-01T11:00',
            'location' => 'Innovation Hub',
            'host' => 'Marian TBI',
        ];
    }
}
