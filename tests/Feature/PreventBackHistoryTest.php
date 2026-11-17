<?php

namespace Tests\Feature;

use App\Http\Middleware\PreventBackHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class PreventBackHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_disable_browser_caching(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-cache')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Cache-Control', 'max-age=0')
            ->assertHeaderContains('Cache-Control', 'must-revalidate')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', 'Sat, 01 Jan 1990 00:00:00 GMT');
    }

    public function test_authenticated_redirects_disable_browser_caching(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertRedirect()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Cache-Control', 'must-revalidate');
    }

    public function test_middleware_sets_headers_on_binary_file_responses(): void
    {
        $response = (new PreventBackHistory)->handle(
            Request::create('/'),
            fn () => new BinaryFileResponse(__FILE__)
        );

        $cacheControl = $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('Sat, 01 Jan 1990 00:00:00 GMT', $response->headers->get('Expires'));
    }

    public function test_authenticated_layout_reloads_when_restored_from_back_forward_cache(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee("window.addEventListener('pageshow'", false)
            ->assertSee('event.persisted', false)
            ->assertSee('window.location.reload()', false)
            ->assertSee('scrubSensitiveInputs', false);
    }
}
