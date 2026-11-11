<?php

namespace Tests\Feature;

use App\Models\Startup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeoutProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_timing(): void
    {
        $mark = static function (string $label, float $t): void {
            fwrite(STDERR, sprintf("\nPROBE %s: %.2fs", $label, microtime(true) - $t));
        };

        $t = microtime(true);
        Startup::ensureConfigured();
        $mark('ensureConfigured', $t);

        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($user);

        $t = microtime(true);
        $this->get(route('technical-needs'));
        $mark('GET folders page (no startup)', $t);

        $t = microtime(true);
        $this->get(route('technical-needs'));
        $mark('GET /technical-needs (canonical)', $t);

        $t = microtime(true);
        $this->get(route('technical-needs', ['startup_id' => 1]));
        $mark('GET startup folder', $t);

        $t = microtime(true);
        $this->post(route('technical-needs.store'), [
            'startup_id' => 1,
            'requirement' => 'Probe need',
            'date_entry' => '2026-10-01',
            'priority' => 'High',
        ]);
        $mark('POST store', $t);

        $t = microtime(true);
        $this->get(route('technical-needs', ['startup_id' => 1]));
        $mark('GET startup folder after store', $t);

        $this->assertTrue(true);
    }
}
