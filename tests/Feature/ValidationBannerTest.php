<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_errors_render_a_single_banner(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)
            ->from(route('incubatees'))
            ->followingRedirects()
            ->post(route('incubatees.store'), []);

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'There were errors with your submission'));
    }
}
