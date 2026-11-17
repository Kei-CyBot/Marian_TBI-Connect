<?php

namespace Tests\Feature;

use App\Http\Middleware\PreventBackHistory;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class BackButtonHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_disable_browser_caching(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', 'Sat, 01 Jan 1990 00:00:00 GMT');
    }

    public function test_middleware_sets_headers_on_streamed_responses(): void
    {
        $response = (new PreventBackHistory)->handle(
            Request::create('/'),
            fn () => new StreamedResponse(fn () => print 'ok')
        );

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }

    public function test_back_button_guard_is_included_in_all_auth_views(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('scrubSensitiveInputs', false)
            ->assertSee("window.addEventListener('pagehide'", false)
            ->assertSee("window.addEventListener('pageshow'", false);

        $this->get(route('invites.accept', $this->invitationToken()))
            ->assertOk()
            ->assertSee('scrubSensitiveInputs', false);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('scrubSensitiveInputs', false);
    }

    public function test_password_inputs_are_never_repopulated(): void
    {
        $this->assertPasswordInputsEmpty($this->get(route('login'))->getContent(), ['password']);
        $this->assertPasswordInputsEmpty($this->get(route('invites.accept', $this->invitationToken()))->getContent(), ['password', 'password_confirmation']);
        $this->assertPasswordInputsEmpty(
            $this->actingAs(User::factory()->create())->get(route('profile.edit'))->getContent(),
            ['current_password', 'new_password', 'new_password_confirmation']
        );
    }

    public function test_authenticated_user_is_redirected_away_from_guest_google_login(): void
    {
        $this->configureGoogle();

        $this->actingAs(User::factory()->create())
            ->get(route('auth.google'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_explicit_profile_account_link_still_starts_google_oauth(): void
    {
        $this->configureGoogle();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('auth.google', ['link' => 1]))
            ->assertRedirect();

        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
        $this->assertStringContainsString('select_account', urldecode($response->headers->get('Location')));
    }

    public function test_replayed_callback_while_authenticated_redirects_to_dashboard(): void
    {
        $this->configureGoogle();
        $this->mockExpiredGoogleState();

        $this->actingAs(User::factory()->create())
            ->withSession(['oauth_register' => ['email' => 'stale@example.test'], 'oauth_state' => 'stale'])
            ->get(route('google.callback'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionMissing('oauth_register')
            ->assertSessionMissing('oauth_state');
    }

    public function test_logout_tears_down_oauth_state_and_expires_session_cookie(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession([
                'google_token' => 'token',
                'google_access_token' => 'token',
                'google_refresh_token' => 'token',
                'oauth_state' => 'state',
                'oauth_register' => ['email' => 'stale@example.test'],
            ])
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('google_token')
            ->assertSessionMissing('oauth_state')
            ->assertCookieExpired(config('session.cookie'));
    }

    private function assertPasswordInputsEmpty(string $html, array $names): void
    {
        foreach ($names as $name) {
            $pattern = '/<input[^>]*name="'.preg_quote($name, '/').'"[^>]*>/';
            $this->assertMatchesRegularExpression($pattern, $html, "Missing {$name} input");
            preg_match($pattern, $html, $matches);
            $this->assertStringContainsString('type="password"', $matches[0]);
            $this->assertStringContainsString('value=""', $matches[0]);
        }
    }

    private function invitationToken(): string
    {
        return TeamInvitation::create([
            'email' => 'invitee@example.test',
            'role' => User::ROLE_MEMBER_INCUBATEE,
            'token' => Str::random(40),
            'expires_at' => now()->addDay(),
            'invited_by' => User::factory()->create()->id,
        ])->token;
    }

    private function configureGoogle(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'https://app.example.test/auth/google/callback',
        ]);
    }

    private function mockExpiredGoogleState(): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new InvalidStateException('Invalid state'));

        $socialite = Mockery::mock(SocialiteManager::class);
        $socialite->shouldReceive('driver')->with('google')->andReturn($provider);
        $this->app->instance(SocialiteFactory::class, $socialite);
    }
}
