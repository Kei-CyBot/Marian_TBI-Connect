<?php

namespace Tests\Feature;

use App\Models\ContactRegistrationApplication;
use App\Models\IntakeSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use Tests\TestCase;

class GoogleDriveOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_logs_in_existing_user_matched_by_email(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
        $user = User::factory()->create(['email' => 'existing@example.test']);
        $this->mockGoogleIdentity('EXISTING@example.test');

        $this->get(route('google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->fresh()->google_id);
        $this->assertSame('https://example.test/avatar.png', $user->fresh()->avatar);
    }

    public function test_callback_sends_pending_applicant_to_status_page(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
        IntakeSubmission::query()->create([
            'type' => 'startup',
            'name' => 'Pending Startup',
            'email' => 'pending@example.test',
            'track' => 'internal',
            'data' => [],
            'status' => 'pending',
        ]);
        $this->mockGoogleIdentity('pending@example.test');

        $this->get(route('google.callback'))
            ->assertRedirect(route('public.apply-status'))
            ->assertSessionHas('info', 'Your application for the Marian TBI Incubation Program is currently under admin review.');
    }

    public function test_callback_recognizes_pending_contact_intake_applicants(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
        ContactRegistrationApplication::query()->create([
            'name' => 'Pending Contact',
            'email' => 'contact-pending@example.test',
            'stream' => 'external',
            'application_data' => [],
            'status' => 'Pending',
        ]);
        $this->mockGoogleIdentity('contact-pending@example.test');

        $this->get(route('google.callback'))
            ->assertRedirect(route('public.apply-status'));
    }

    public function test_callback_creates_a_user_and_contact_for_new_google_accounts(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
        $this->mockGoogleIdentity('new@example.test');

        $this->get(route('google.callback'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Welcome to Marian TBI Connect! Your account was created with Google.');

        $user = User::query()->where('email', 'new@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::ROLE_MEMBER_INCUBATEE, $user->role);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('https://example.test/avatar.png', $user->avatar);
        $this->assertDatabaseHas('contacts', [
            'email' => 'new@example.test',
            'name' => 'Google Applicant',
            'side' => 'external',
        ]);
    }

    public function test_callback_matches_an_existing_user_by_google_id_before_email(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
        $user = User::factory()->create([
            'email' => 'old@example.test',
            'google_id' => 'google-123',
        ]);
        $this->mockGoogleIdentity('new-email@example.test');

        $this->get(route('google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('new-email@example.test', $user->fresh()->google_email);
    }

    public function test_callback_redirects_to_login_when_consent_is_denied(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);

        $this->get(route('google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google sign-in was cancelled or denied. Please try again.');
    }

    public function test_callback_redirects_to_login_on_state_mismatch(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new InvalidStateException('Invalid state'));
        $socialite = Mockery::mock(SocialiteManager::class);
        $socialite->shouldReceive('driver')->with('google')->andReturn($provider);
        $this->app->instance(SocialiteFactory::class, $socialite);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your Google sign-in session expired. Please start again.');
    }

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

            public function getEmail(): string
            {
                return 'member@example.test';
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
        $this->assertSame('member@example.test', $user->google_email);
        $this->assertSame('access-token', $user->google_access_token);
        $this->assertSame('refresh-token', $user->google_refresh_token);
        $this->assertTrue($user->google_token_expires_at->isFuture());
    }

    public function test_connecting_a_google_account_already_linked_to_another_user_moves_the_link(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
        $previousOwner = User::factory()->create(['google_id' => 'google-123']);
        $user = User::factory()->create();
        $this->mockGoogleIdentity('member@example.test');

        $this->actingAs($user)
            ->get(route('google.callback'))
            ->assertRedirect(route('dashboard'));

        $this->assertNull($previousOwner->refresh()->google_id);
        $this->assertSame('google-123', $user->refresh()->google_id);
    }

    public function test_google_disconnect_clears_linked_credentials_and_confirms_success(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'google_id' => 'google-123',
            'google_email' => 'google@example.test',
            'google_access_token' => 'access-token',
            'google_refresh_token' => 'refresh-token',
            'google_token_expires_at' => now()->addHour(),
        ])->save();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('auth.google.disconnect'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Your Google Account has been unlinked. System operations will use central Marian TBI credentials.');

        $user->refresh();
        $this->assertNull($user->google_id);
        $this->assertNull($user->google_email);
        $this->assertNull($user->google_access_token);
        $this->assertNull($user->google_refresh_token);
        $this->assertNull($user->google_token_expires_at);
    }

    public function test_google_identity_prefills_and_locks_application_email(): void
    {
        $this->withSession(['oauth_register' => [
            'name' => 'Google Applicant',
            'email' => 'applicant@example.test',
            'avatar' => 'https://example.test/avatar.png',
            'google_id' => 'google-123',
        ]])
            ->get(route('public.startup-form'))
            ->assertOk()
            ->assertSee('value="Google Applicant"', false)
            ->assertSee('value="applicant@example.test"', false)
            ->assertSee('readonly', false)
            ->assertSee('Verified via Google Account');
    }

    private function mockGoogleIdentity(string $email): void
    {
        $googleUser = new class($email)
        {
            public string $token = 'access-token';

            public string $refreshToken = 'refresh-token';

            public int $expiresIn = 3600;

            public function __construct(private string $email) {}

            public function getId(): string
            {
                return 'google-123';
            }

            public function getEmail(): string
            {
                return $this->email;
            }

            public function getName(): string
            {
                return 'Google Applicant';
            }

            public function getAvatar(): string
            {
                return 'https://example.test/avatar.png';
            }
        };
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        $socialite = Mockery::mock(SocialiteManager::class);
        $socialite->shouldReceive('driver')->with('google')->andReturn($provider);
        $this->app->instance(SocialiteFactory::class, $socialite);
    }
}
