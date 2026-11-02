from pathlib import Path

BS = chr(92)
p = Path('tests/Feature/GoogleDriveOAuthTest.php')
s = p.read_text()
s = s.replace('use App' + BS + 'Models' + BS + 'User;',
              'use App' + BS + 'Models' + BS + 'IntakeSubmission;' + chr(10) + 'use App' + BS + 'Models' + BS + 'User;', 1)
s = s.replace("""            public function getId(): string
            {
                return 'google-123';
            }
        });""", """            public function getId(): string
            {
                return 'google-123';
            }

            public function getEmail(): string
            {
                return 'google-123@example.test';
            }

            public function getName(): string
            {
                return 'Google User';
            }
        });""", 1)

extra = r'''
    private function fakeGoogleUser(string $email, string $name = 'Google User'): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn(new class($email, $name)
        {
            public string $token = 'access-token';

            public string $refreshToken = 'refresh-token';

            public int $expiresIn = 3600;

            public function __construct(private string $email, private string $name) {}

            public function getId(): string
            {
                return 'google-'.$this->email;
            }

            public function getEmail(): string
            {
                return $this->email;
            }

            public function getName(): string
            {
                return $this->name;
            }
        });

        $socialite = Mockery::mock(SocialiteManager::class);
        $socialite->shouldReceive('driver')->with('google')->andReturn($provider);
        $this->app->instance(SocialiteFactory::class, $socialite);
    }

    public function test_google_callback_logs_in_an_existing_user_by_email(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);

        $user = User::factory()->create(['email' => 'existing@example.test']);
        $this->fakeGoogleUser('existing@example.test');

        $this->get(route('google.callback'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Google Account Connected!');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('access-token', $user->refresh()->google_access_token);
    }

    public function test_google_callback_sends_pending_applicants_to_the_status_page(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);

        IntakeSubmission::query()->create([
            'type' => 'startup',
            'name' => 'Pending Startup',
            'email' => 'pending-applicant@example.test',
            'track' => 'internal',
            'data' => [],
            'status' => 'Pending',
        ]);

        $this->fakeGoogleUser('pending-applicant@example.test');

        $this->get(route('google.callback'))
            ->assertRedirect(route('apply.status'))
            ->assertSessionHas('info', 'Your application for Marian TBI is currently under admin review.');

        $this->assertGuest();
    }

    public function test_google_callback_sends_new_visitors_to_the_prefilled_application_form(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);

        $this->fakeGoogleUser('new-applicant@example.test', 'New Applicant');

        $this->get(route('google.callback'))
            ->assertRedirect(route('public.apply'))
            ->assertSessionHas('oauth_register', [
                'name' => 'New Applicant',
                'email' => 'new-applicant@example.test',
            ]);

        $this->assertGuest();
    }

    public function test_application_form_prefills_and_locks_the_google_email(): void
    {
        $this->withSession(['oauth_register' => [
            'name' => 'New Applicant',
            'email' => 'new-applicant@example.test',
        ]])->get(route('public.apply'))
            ->assertOk()
            ->assertSee('New Applicant')
            ->assertSee('new-applicant@example.test')
            ->assertSee('readonly="readonly"', false);
    }

    public function test_disconnecting_google_clears_the_stored_tokens(): void
    {
        $user = User::factory()->create([
            'google_id' => 'google-123',
            'google_access_token' => 'access-token',
            'google_refresh_token' => 'refresh-token',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $this->actingAs($user)
            ->post(route('google.disconnect'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('profile_status', 'Google account disconnected.');

        $user->refresh();

        $this->assertNull($user->google_id);
        $this->assertNull($user->google_access_token);
        $this->assertNull($user->google_refresh_token);
        $this->assertNull($user->google_token_expires_at);
    }
'''

s = s.rstrip()
assert s.endswith('}')
s = s[:-1].rstrip() + chr(10) + extra + '}' + chr(10)
p.write_text(s)

p2 = Path('tests/Feature/MockResolutionTest.php')
t = p2.read_text()
extra2 = r'''
    public function test_intake_receipt_includes_a_tracking_id(): void
    {
        $submission = \App\Models\IntakeSubmission::query()->create([
            'type' => 'startup',
            'name' => 'Tracking Co',
            'email' => 'tracking@example.test',
            'track' => 'internal',
            'data' => [],
        ]);

        $html = (new \App\Mail\IntakeSubmissionReceived($submission))->render();

        $this->assertStringContainsString('MTBI-S-', $html);
        $this->assertStringContainsString($submission->trackingId(), $html);
    }
'''
t = t.rstrip()
assert t.endswith('}')
t = t[:-1].rstrip() + chr(10) + extra2 + '}' + chr(10)
p2.write_text(t)
