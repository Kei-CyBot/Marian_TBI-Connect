<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery;
use Tests\TestCase;

class GoogleDriveOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_uses_the_configured_marian_tbi_redirect_uri(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.drive_account_email' => 'drive@example.test',
            'services.google.drive_redirect' => 'https://connect.example.test/google-drive/callback',
            'services.google.redirect' => 'https://connect.example.test/auth/google/callback',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('google-drive.connect'));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'redirect_uri='.urlencode('https://connect.example.test/google-drive/callback'),
            $response->headers->get('Location')
        );
    }

    public function test_callback_stores_google_tokens_on_the_authenticated_user(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);

        $user = User::factory()->create();

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn(new class
        {
            public string $token = 'access-token';

            public string $refreshToken = 'refresh-token';

            public int $expiresIn = 3600;

            public function getId(): string
            {
                return 'google-123';
            }
        });

        $socialite = Mockery::mock(SocialiteManager::class);
        $socialite->shouldReceive('driver')->with('google')->andReturn($provider);
        $this->app->instance(SocialiteFactory::class, $socialite);

        $this->actingAs($user)
            ->get(route('google.callback'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Google Account Connected!');

        $user->refresh();

        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('access-token', $user->google_access_token);
        $this->assertSame('refresh-token', $user->google_refresh_token);
        $this->assertTrue($user->google_token_expires_at->isFuture());
    }
}
