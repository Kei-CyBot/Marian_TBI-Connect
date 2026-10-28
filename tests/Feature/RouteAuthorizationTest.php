<?php

namespace Tests\Feature;

use App\Enums\Forms\ApprovalStatus;
use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\FormSubmission;
use App\Models\Startup;
use App\Models\StartupDriveLink;
use App\Models\TeamInvitation;
use App\Models\TechnicalNeed;
use App\Models\User;
use App\Support\GoogleDriveService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private User $member;

    private Startup $startup;

    private FormSubmission $submission;

    private Contact $contact;

    private ContactRegistrationApplication $application;

    private TeamInvitation $invitation;

    private StartupDriveLink $driveLink;

    private TechnicalNeed $technicalNeed;

    private Startup $trashedStartup;

    private Contact $trashedContact;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'mail.default' => 'array',
            'services.google.client_id' => 'route-client-id',
            'services.google.client_secret' => 'route-client-secret',
            'services.google.drive_account_email' => 'drive@example.test',
            'services.google.drive_redirect' => 'https://connect.example.test/google-drive/callback',
            'services.google.redirect' => 'https://connect.example.test/auth/google/callback',
        ]);

        Startup::ensureConfigured();
        $this->startup = Startup::query()->findOrFail(1);
        $this->startup->forceFill(['google_drive_folder_id' => 'coverage-folder'])->save();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'email' => 'route-admin@example.test']);
        $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'email' => 'route-super-admin@example.test']);
        $this->member = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE, 'email' => 'route-member@example.test']);

        Storage::disk('local')->put('form-attachments/coverage.pdf', 'coverage');
        Storage::disk('local')->put('technical-needs/coverage.pdf', 'coverage');

        $this->submission = FormSubmission::query()->create([
            'user_id' => $this->admin->id,
            'form_id' => 2,
            'form_name' => 'Startup profile',
            'startup_name' => $this->startup->name,
            'track' => $this->startup->track,
            'data' => ['business_name' => $this->startup->name],
            'approval_status' => ApprovalStatus::Submitted,
            'prerequisite_path' => 'form-attachments/coverage.pdf',
            'prerequisite_original_name' => 'coverage.pdf',
            'prerequisite_mime_type' => 'application/pdf',
            'prerequisite_size_bytes' => 8,
        ]);
        $this->contact = Contact::query()->create([
            'side' => 'external',
            'name' => 'Route Coverage Contact',
            'email' => 'contact@example.test',
            'affiliation_type' => 'Company',
            'role' => 'Partner',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
        ]);
        $this->application = ContactRegistrationApplication::query()->create([
            'name' => 'Route Applicant',
            'email' => 'applicant@example.test',
            'stream' => 'internal',
            'application_data' => [
                'stream' => 'internal',
                'affiliationType' => 'Other',
                'role' => 'Other',
                'domains' => [],
                'technicalSkills' => [],
            ],
        ]);
        $this->invitation = TeamInvitation::query()->create([
            'email' => 'route-invite@example.test',
            'role' => User::ROLE_ADMIN,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'invited_by' => $this->admin->id,
        ]);
        $this->driveLink = StartupDriveLink::query()->create([
            'startup_id' => $this->startup->id,
            'startup_name' => $this->startup->name,
            'label' => 'Coverage Drive',
            'url' => 'https://drive.google.com/drive/folders/coverage',
            'user_id' => $this->admin->id,
        ]);
        $this->technicalNeed = TechnicalNeed::query()->create([
            'startup_id' => $this->startup->id,
            'requirement' => 'Route Coverage Need',
            'date_entry' => '2026-01-01',
            'status' => 'Ongoing',
            'priority' => 'High',
            'approval_document_path' => 'technical-needs/coverage.pdf',
            'approval_document_original_name' => 'coverage.pdf',
        ]);
        $this->trashedStartup = Startup::query()->create([
            'name' => 'Route Archived Startup',
            'founder' => 'Archived Founder',
            'contact_email' => 'archived-startup@example.test',
            'track' => 'external',
            'status' => 'Applicant',
            'cohort_id' => 1,
        ]);
        $this->trashedStartup->delete();
        $this->trashedContact = Contact::query()->create([
            'side' => 'external',
            'name' => 'Route Archived Contact',
            'email' => 'archived-contact@example.test',
            'affiliation_type' => 'Company',
            'role' => 'Partner',
            'domains' => [],
            'technical_skills' => [],
            'incubatees' => [],
            'activity_log' => [],
        ]);
        $this->trashedContact->delete();
    }

    public static function adminRouteProvider(): array
    {
        return [
            'activity logs' => ['activity-logs.index', 'get', [], [], 'ok'],
            'form submissions store' => ['form-submissions.store', 'post', [], [], 'redirect'],
            'form submissions update' => ['form-submissions.update', 'put', ['submission' => 'submission'], [], 'redirect'],
            'form submissions status' => ['form-submissions.status', 'patch', ['submission' => 'submission'], ['status' => 'approved'], 'redirect'],
            'form submissions destroy' => ['form-submissions.destroy', 'delete', ['submission' => 'submission'], [], 'redirect'],
            'form submissions move' => ['form-submissions.move', 'post', ['submission' => 'submission'], ['target_folder_id' => 'coverage-folder'], 'redirect'],
            'incubatees store' => ['incubatees.store', 'post', [], [], 'redirect'],
            'incubatees update' => ['incubatees.update', 'put', ['startup' => 'startup'], [], 'redirect'],
            'incubatees destroy' => ['incubatees.destroy', 'delete', ['startup' => 'startup'], [], 'redirect'],
            'startup assessments store' => ['startup-assessments.store', 'post', [], [], 'redirect'],
            'startup drive links store' => ['startup-drive-links.store', 'post', [], [], 'redirect'],
            'startup drive links destroy' => ['startup-drive-links.destroy', 'delete', ['driveLink' => 'driveLink'], [], 'redirect'],
            'contacts index' => ['contacts', 'get', [], [], 'ok'],
            'contacts store' => ['contacts.store', 'post', [], [], 'redirect'],
            'contacts update' => ['contacts.update', 'put', ['contact' => 'contact'], [], 'redirect'],
            'contacts destroy' => ['contacts.destroy', 'delete', ['contact' => 'contact'], [], 'redirect'],
            'contact assignments store' => ['contacts.assignments.store', 'post', ['contact' => 'contact'], [], 'redirect'],
            'contact assignments destroy' => ['contacts.assignments.destroy', 'delete', ['contact' => 'contact', 'startup' => 'startup'], [], 'redirect'],
            'contact intake index' => ['contact-intake', 'get', [], [], 'ok'],
            'contact intake approve' => ['contact-intake.approve', 'post', ['application' => 'application'], [], 'redirect'],
            'contact intake provision' => ['contact-intake.provision', 'post', ['application' => 'application'], [], 'redirect'],
            'contact intake destroy' => ['contact-intake.destroy', 'delete', ['application' => 'application'], [], 'redirect'],
            'form submissions export' => ['exports.form-submissions', 'get', [], [], 'ok'],
            'technical needs export' => ['exports.technical-needs', 'get', [], [], 'ok'],
            'google drive connect' => ['google-drive.connect', 'get', [], [], 'redirect'],
            'google drive callback' => ['google-drive.callback', 'get', [], [], 'redirect'],
            'google drive sync' => ['google-drive.sync', 'post', [], [], 'redirect'],
            'startup folder access' => ['startup-folders.access.update', 'post', ['startup' => 'startup'], ['restricted' => '0', 'user_ids' => []], 'redirect'],
            'team invite' => ['team.invite', 'post', [], ['email' => 'route-new-member@example.test', 'role' => User::ROLE_ADMIN], 'redirect'],
            'team invitation revoke' => ['team.invitations.revoke', 'delete', ['invitation' => 'invitation'], [], 'redirect'],
            'archives index' => ['archives', 'get', [], [], 'ok'],
            'archived startup restore' => ['archives.startups.restore', 'post', ['startup' => 'trashedStartup'], [], 'redirect'],
            'archived startup destroy' => ['archives.startups.destroy', 'delete', ['startup' => 'trashedStartup'], [], 'redirect'],
            'archived contact restore' => ['archives.contacts.restore', 'post', ['contact' => 'trashedContact'], [], 'redirect'],
            'archived contact destroy' => ['archives.contacts.destroy', 'delete', ['contact' => 'trashedContact'], [], 'redirect'],
            'technical needs store' => ['technical-needs.store', 'post', [], [], 'redirect'],
            'technical needs update' => ['technical-needs.update', 'put', ['technicalNeed' => 'technicalNeed'], [], 'redirect'],
            'technical needs destroy' => ['technical-needs.destroy', 'delete', ['technicalNeed' => 'technicalNeed'], [], 'redirect'],
            'event email send' => ['event-emails.send', 'post', [], [], 'redirect'],
            'team role update' => ['team.roles.update', 'put', ['user' => 'member'], ['role' => User::ROLE_ADMIN], 'redirect'],
        ];
    }

    public static function authenticatedRouteProvider(): array
    {
        return [
            'dashboard' => ['dashboard', 'get', [], [], 'ok'],
            'files' => ['files', 'get', [], [], 'ok'],
            'submission attachment' => ['form-submissions.attachment', 'get', ['submission' => 'submission'], [], 'ok'],
            'incubatees' => ['incubatees', 'get', [], [], 'ok'],
            'profile edit' => ['profile.edit', 'get', [], [], 'ok'],
            'profile update' => ['profile.update', 'put', [], [], 'redirect'],
            'technical needs index' => ['technical-needs', 'get', [], [], 'ok'],
            'technical need approval' => ['technical-needs.approval', 'get', ['technicalNeed' => 'technicalNeed'], [], 'ok'],
            'events' => ['events', 'get', [], [], 'ok'],
            'team' => ['team', 'get', [], [], 'ok'],
            'logout' => ['logout', 'post', [], [], 'redirect'],
        ];
    }

    public static function publicRouteProvider(): array
    {
        return [
            'home' => ['home', 'get', [], [], 'ok'],
            'startup form' => ['startup-form', 'get', [], [], 'ok'],
            'startup form store' => ['startup-form.store', 'post', [], [], 'redirect'],
            'alumni form' => ['alumni-form', 'get', [], [], 'ok'],
            'alumni form store' => ['alumni-form.store', 'post', [], [], 'redirect'],
            'contact registration form' => ['contact-registration', 'get', [], [], 'ok'],
            'contact registration store' => ['contact-registration.store', 'post', [], [], 'redirect'],
            'login form' => ['login', 'get', [], [], 'ok'],
            'login store' => ['login.store', 'post', [], [], 'redirect'],
            'invite form' => ['invites.accept', 'get', ['token' => 'invitationToken'], [], 'ok'],
            'invite accept' => ['invites.accept.process', 'post', ['token' => 'invitationToken'], [], 'redirect'],
            'google login redirect' => ['auth.google.redirect', 'get', [], [], 'redirect'],
            'google login callback' => ['auth.google.callback', 'get', [], [], 'redirect'],
        ];
    }

    #[DataProvider('adminRouteProvider')]
    public function test_admin_routes_allow_managers_and_reject_regular_members(string $routeName, string $method, array $params, array $payload, string $expectedStatus): void
    {
        $this->prepareRouteScenario($routeName);
        $actor = $routeName === 'team.roles.update' ? $this->superAdmin : $this->admin;
        $blockedActor = $routeName === 'team.roles.update' ? $this->admin : $this->member;
        $url = $this->routeUrl($routeName, $params);
        $payload = $this->payloadFor($routeName, $payload);

        $this->actingAs($blockedActor)->{$method}($url, $payload)->assertForbidden();

        $response = $this->actingAs($actor)->{$method}($url, $payload);
        $this->assertAuthorizedResponse($response, $expectedStatus, $routeName);
    }

    public function test_credential_routes_are_super_admin_only(): void
    {
        $this->prepareRouteScenario('contacts');
        $contact = $this->contact;

        $this->actingAs($this->member)
            ->patch($this->routeUrl('contacts.credentials.update', ['contact' => 'contact']), [])
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->patch($this->routeUrl('contacts.credentials.update', ['contact' => 'contact']), [])
            ->assertForbidden();

        $response = $this->actingAs($this->superAdmin)
            ->patch($this->routeUrl('contacts.credentials.update', ['contact' => 'contact']), ['email' => $contact->email, 'password' => 'password123']);
        $this->assertTrue(in_array($response->status(), [200, 302], true));

        $this->actingAs($this->member)
            ->post($this->routeUrl('contacts.credentials.send', ['contact' => 'contact']), [])
            ->assertForbidden();
        $this->actingAs($this->admin)
            ->post($this->routeUrl('contacts.credentials.send', ['contact' => 'contact']), [])
            ->assertForbidden();

        Mail::fake();
        $response = $this->actingAs($this->superAdmin)
            ->post($this->routeUrl('contacts.credentials.send', ['contact' => 'contact']), []);
        $this->assertTrue(in_array($response->status(), [200, 302], true));
    }

    #[DataProvider('authenticatedRouteProvider')]
    public function test_authenticated_routes_allow_members_and_reject_guests(string $routeName, string $method, array $params, array $payload, string $expectedStatus): void
    {
        $url = $this->routeUrl($routeName, $params);
        $payload = $this->payloadFor($routeName, $payload);

        $this->{$method}($url, $payload)->assertRedirect(route('login'));

        $response = $this->actingAs($this->member)->{$method}($url, $payload);
        $this->assertAuthorizedResponse($response, $expectedStatus, $routeName);
    }

    #[DataProvider('publicRouteProvider')]
    public function test_public_routes_are_reachable_without_authentication(string $routeName, string $method, array $params, array $payload, string $expectedStatus): void
    {
        if (in_array($routeName, ['auth.google.redirect', 'auth.google.callback'], true)) {
            $this->fakeGoogleLogin();
        }

        $url = $this->routeUrl($routeName, $params);
        $payload = $this->payloadFor($routeName, $payload);

        $response = $this->{$method}($url, $payload);
        $this->assertAuthorizedResponse($response, $expectedStatus, $routeName);
    }

    public function test_guest_only_routes_redirect_authenticated_users(): void
    {
        foreach ([
            route('home'),
            route('login'),
            route('invites.accept', $this->invitation->token),
            route('auth.google.redirect'),
            route('auth.google.callback'),
        ] as $url) {
            $this->actingAs($this->member)->get($url)->assertRedirect(route('dashboard'));
        }

        $this->actingAs($this->member)
            ->post(route('invites.accept.process', $this->invitation->token), [])
            ->assertRedirect(route('dashboard'));
    }

    public function test_public_write_routes_reject_invalid_guest_input(): void
    {
        $this->from(route('startup-form'))
            ->post(route('startup-form.store'), ['stream' => 'academic'])
            ->assertRedirect(route('startup-form'))
            ->assertSessionHasErrors(['name', 'founder', 'email', 'sector']);

        $this->from(route('alumni-form'))
            ->post(route('alumni-form.store'), ['stream' => 'industry'])
            ->assertRedirect(route('alumni-form'))
            ->assertSessionHasErrors(['name', 'startup', 'email', 'status']);

        $this->from(route('contact-registration'))
            ->post(route('contact-registration.store'), ['stream' => 'internal'])
            ->assertRedirect(route('contact-registration'))
            ->assertSessionHasErrors(['name', 'email']);

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->post(route('invites.accept.process', 'missing-token'), [
            'name' => 'Missing Invite',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();
    }

    public function test_route_authorization_matrix_covers_every_web_route(): void
    {
        $covered = collect([
            ...array_column(self::adminRouteProvider(), 0),
            ...array_column(self::authenticatedRouteProvider(), 0),
            ...array_column(self::publicRouteProvider(), 0),
        ]);

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->getName() !== null && ! str_starts_with($route->uri(), 'storage/'))
            ->map(fn ($route): string => (string) $route->getName())
            ->filter(fn ($name): bool => ! in_array($name, ['contacts.credentials.update', 'contacts.credentials.send'], true))
            ->sort()
            ->values();

        $this->assertSame($routes->all(), $covered->sort()->values()->all());
    }

    private function assertAuthorizedResponse($response, string $expectedStatus, string $routeName): void
    {
        if ($expectedStatus === 'ok') {
            $response->assertOk();

            return;
        }

        $response->assertRedirect();
        $this->assertNotSame(403, $response->status(), $routeName.' rejected an authorized request.');
    }

    private function routeUrl(string $routeName, array $params): string
    {
        $resolved = [];
        foreach ($params as $key => $property) {
            if ($property === 'invitationToken') {
                $resolved[$key] = $this->invitation->token;

                continue;
            }

            $value = $this->{$property};
            $resolved[$key] = $value instanceof Model
                ? $value->getRouteKey()
                : $value;
        }

        return route($routeName, $resolved);
    }

    private function payloadFor(string $routeName, array $payload): array
    {
        return match ($routeName) {
            'form-submissions.store' => [
                'form_id' => 2,
                'startup' => $this->startup->name,
                'track' => $this->startup->track,
                'signer_name' => 'Route Signer',
                'data' => json_encode(['founder' => 'Route Founder'], JSON_THROW_ON_ERROR),
            ],
            'form-submissions.update' => [
                'form_id' => 2,
                'startup' => $this->startup->name,
                'track' => $this->startup->track,
                'signer_name' => 'Route Signer',
                'data' => ['founder' => 'Route Founder'],
            ],
            'incubatees.store' => $this->startupPayload('Route Coverage Startup'),
            'incubatees.update' => $this->startupPayload('Route Coverage Updated Startup'),
            'startup-assessments.store' => $this->assessmentPayload(),
            'startup-drive-links.store' => [
                'startup_id' => $this->startup->id,
                'label' => 'Route Coverage Drive',
                'url' => 'https://drive.google.com/drive/folders/route-coverage',
            ],
            'contacts.store' => $this->contactPayload('Route New Contact', 'route-new-contact@example.test'),
            'contacts.update' => $this->contactPayload('Route Updated Contact', $this->contact->email),
            'contacts.assignments.store' => [
                'startup_id' => $this->startup->id,
                'verification_method' => 'program_records',
                'verification_notes' => 'Covered by the route authorization matrix.',
            ],
            'technical-needs.store' => $this->technicalNeedPayload() + [
                'signed_approval' => UploadedFile::fake()->create('coverage.pdf', 10, 'application/pdf'),
            ],
            'technical-needs.update' => $this->technicalNeedPayload(),
            'event-emails.send' => [
                'contact_ids' => [$this->contact->id],
                'subject' => 'Route coverage',
                'body' => 'Route coverage message.',
            ],
            'profile.update' => [
                'name' => $this->member->name,
                'email' => $this->member->email,
                'current_password' => 'password',
            ],
            'startup-form.store' => [
                'stream' => 'academic',
                'name' => 'Route Startup Application',
                'founder' => 'Route Student',
                'email' => 'route-startup@example.test',
                'sector' => 'Edtech',
            ],
            'alumni-form.store' => [
                'stream' => 'industry',
                'name' => 'Route Alumnus',
                'startup' => 'Route Alumni Startup',
                'email' => 'route-alumni@example.test',
                'status' => 'Operating',
            ],
            'contact-registration.store' => [
                'stream' => 'internal',
                'name' => 'Route Registration',
                'email' => 'route-registration@example.test',
                'affiliation_type' => 'University department / office',
                'organization' => 'College of Computing',
                'role' => 'Student Lead',
                'academic_program' => 'BS Computer Science',
                'academic_year' => '3rd year',
                'domains' => ['Information Technology'],
            ],
            'login.store' => [
                'email' => $this->member->email,
                'password' => 'password',
            ],
            'invites.accept.process' => [
                'name' => 'Route Invited Member',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
            default => $payload,
        };
    }

    private function startupPayload(string $name): array
    {
        return [
            'name' => $name,
            'founder' => 'Route Founder',
            'email' => Str::slug($name).'@example.test',
            'track' => 'external',
            'status' => 'Applicant',
            'cohort_id' => 1,
            'venture_type' => 'Commercial Startup',
        ];
    }

    private function contactPayload(string $name, ?string $email): array
    {
        return [
            'side' => 'external',
            'name' => $name,
            'email' => $email,
            'affiliation_type' => 'Company',
            'role' => 'Partner',
            'organization' => 'Route Coverage Co',
            'domains' => ['Information Technology'],
            'technical_skills' => [],
            'nda_status' => 'Pending',
        ];
    }

    private function technicalNeedPayload(): array
    {
        return [
            'startup_id' => $this->startup->id,
            'requirement' => 'Route Coverage Requirement',
            'date_entry' => '2026-01-01',
            'status' => 'Ongoing',
            'priority' => 'High',
        ];
    }

    private function assessmentPayload(): array
    {
        $keys = collect(config('startup_assessment'))
            ->flatMap(static fn (array $area): array => array_column($area['competencies'], 'key'));
        $ratings = $keys->mapWithKeys(static fn (string $key): array => [$key => 3])->all();

        return [
            'startup_id' => (string) $this->startup->id,
            'startup_name' => $this->startup->name,
            'data' => [
                'competencyRatings' => $ratings,
                'otherCriticalSkills' => ['', '', '', '', ''],
            ],
        ];
    }

    private function prepareRouteScenario(string $routeName): void
    {
        if (in_array($routeName, ['google-drive.connect', 'google-drive.callback'], true)) {
            $provider = Mockery::mock(GoogleProvider::class);
            $provider->shouldReceive('scopes')->andReturnSelf();
            $provider->shouldReceive('with')->andReturnSelf();
            $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
            $provider->shouldReceive('user')->andReturn($this->googleUser('drive@example.test'));

            $socialite = Mockery::mock(SocialiteManager::class);
            $socialite->shouldReceive('buildProvider')->andReturn($provider);
            $this->app->instance(SocialiteManager::class, $socialite);
        }

        if ($routeName === 'google-drive.sync') {
            $this->mock(GoogleDriveService::class, function ($mock): void {
                $mock->shouldReceive('isConnected')->andReturn(true);
                $mock->shouldReceive('syncAll')->andReturn(['folders' => 0, 'forms' => 0]);
            });
        }
    }

    private function fakeGoogleLogin(): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        $provider->shouldReceive('user')->andReturn($this->googleUser($this->member->email));

        $socialite = Mockery::mock(SocialiteManager::class);
        $socialite->shouldReceive('driver')->with('google')->andReturn($provider);
        $this->app->instance(SocialiteFactory::class, $socialite);
    }

    private function googleUser(string $email): object
    {
        return new class($email)
        {
            public array $user = ['verified_email' => true];

            public string $token = 'route-access-token';

            public string $refreshToken = 'route-refresh-token';

            public int $expiresIn = 3600;

            public function __construct(private string $email) {}

            public function getEmail(): string
            {
                return $this->email;
            }
        };
    }
}
