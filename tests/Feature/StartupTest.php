<?php

namespace Tests\Feature;

use App\Mail\AccountProvisionedMail;
use App\Models\IntakeSubmission;
use App\Models\Startup;
use App\Models\User;
use App\Support\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StartupTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_and_pending_tabs_render_their_own_records(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Startup::ensureConfigured();

        IntakeSubmission::query()->create([
            'type' => 'startup',
            'name' => 'Pending Nova Foods',
            'email' => 'pending@example.com',
            'track' => 'external',
            'data' => [
                'name' => 'Pending Nova Foods',
                'founder' => 'Nina Cruz',
                'email' => 'pending@example.com',
                'sector' => 'Agritech',
                'pitch' => 'A pending application for the queue.',
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('incubatees'))
            ->assertOk()
            ->assertSee('Active Incubatees')
            ->assertSee('AgriSense Labs')
            ->assertDontSee('A pending application for the queue.');

        $this->actingAs($admin)
            ->get(route('incubatees', ['tab' => 'pending']))
            ->assertOk()
            ->assertSee('Pending Applications')
            ->assertSee('Pending Nova Foods')
            ->assertSee('Nina Cruz')
            ->assertSee('pending@example.com')
            ->assertSee('Approve &amp; Onboard', false);
    }

    public function test_members_cannot_view_pending_intake_submissions(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);

        IntakeSubmission::query()->create([
            'type' => 'startup',
            'name' => 'Hidden Pending Startup',
            'email' => 'hidden@example.com',
            'track' => 'external',
            'data' => ['name' => 'Hidden Pending Startup', 'email' => 'hidden@example.com'],
        ]);

        $this->actingAs($member)
            ->get(route('incubatees', ['tab' => 'pending']))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('startups.index', ['tab' => 'pending']))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('incubatees'))
            ->assertOk()
            ->assertDontSee('Hidden Pending Startup');
    }

    public function test_approving_a_pending_application_onboards_it_and_provisions_drive_and_founder_account(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $submission = IntakeSubmission::query()->create([
            'type' => 'startup',
            'name' => 'Nova Foods',
            'email' => 'nova@example.com',
            'track' => 'external',
            'data' => [
                'stream' => 'industry',
                'name' => 'Nova Foods',
                'founder' => 'Nina Cruz',
                'email' => 'nova@example.com',
                'sector' => 'Agritech',
                'pitch' => 'Shelf-stable plant protein.',
                'entity' => 'Inc.',
            ],
        ]);

        $this->mock(GoogleDriveService::class, function ($mock): void {
            $mock->shouldReceive('ensureStartupFolder')
                ->once()
                ->andReturn([
                    'id' => 'drive-folder-123',
                    'url' => 'https://drive.google.com/drive/folders/drive-folder-123',
                ]);
        });

        $this->actingAs($admin)
            ->post(route('startup-intake.approve', $submission))
            ->assertRedirect(route('incubatees', ['tab' => 'active']))
            ->assertSessionHas('startup_status');

        $this->assertSame('approved', $submission->refresh()->status);

        $startup = Startup::query()->where('name', 'Nova Foods')->sole();
        $this->assertSame('Nina Cruz', $startup->founder);
        $this->assertSame('nova@example.com', $startup->contact_email);
        $this->assertSame('Incubatee', $startup->status);
        $this->assertSame('drive-folder-123', $startup->google_drive_folder_id);
        $this->assertSame('https://drive.google.com/drive/folders/drive-folder-123', $startup->google_drive_folder_url);

        $founder = User::query()->where('email', 'nova@example.com')->sole();
        $this->assertSame(User::ROLE_MEMBER_INCUBATEE, $founder->role);
        $this->assertTrue($founder->isMember());
        $this->assertFalse($founder->canManageRecords());
        Mail::assertQueued(AccountProvisionedMail::class, fn (AccountProvisionedMail $mail): bool => $mail->hasTo('nova@example.com'));

        $this->actingAs($founder)
            ->post(route('startup-intake.approve', $submission))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('incubatees', ['tab' => 'active']))
            ->assertOk()
            ->assertSee('Nova Foods');
    }

    public function test_public_form_collects_essential_intake_fields(): void
    {
        $this->get(route('public.startup-form'))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="internal_external"', false)
            ->assertSee('name="startup_name"', false)
            ->assertSee('name="description"', false)
            ->assertSee('Return to Login')
            ->assertDontSee('name="affiliation"', false)
            ->assertDontSee('name="organization"', false)
            ->assertDontSee('name="drive_link"', false);
    }

    public function test_intake_form_accepts_minimal_name_and_email(): void
    {
        Mail::fake();

        $this->post(route('startup-form.store'), [
            'name' => 'Solo Founder',
            'email' => 'solo@example.com',
        ])->assertRedirect(route('public.startup-form'));

        $submission = IntakeSubmission::query()->where('email', 'solo@example.com')->sole();
        $this->assertSame('Solo Founder', $submission->name);
        $this->assertSame('external', $submission->track);
    }

    public function test_approval_transfers_public_intake_fields_to_startup_and_user(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->post(route('startup-form.store'), [
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'phone' => '+639171234567',
            'internal_external' => 'Internal',
            'startup_name' => 'Campus Robotics',
            'description' => 'Low-cost robotics kits for public schools.',
        ])->assertRedirect(route('public.startup-form'));

        $submission = IntakeSubmission::query()->where('email', 'maria@example.com')->sole();
        $this->assertSame('Internal', $submission->data['internal_external']);

        $this->mock(GoogleDriveService::class, function ($mock): void {
            $mock->shouldReceive('ensureStartupFolder')->once()->andReturn([
                'id' => 'intake-folder-1',
                'url' => 'https://drive.google.com/drive/folders/intake-folder-1',
            ]);
        });

        $this->actingAs($admin)
            ->post(route('startup-intake.approve', $submission))
            ->assertRedirect(route('incubatees', ['tab' => 'active']))
            ->assertSessionHas('startup_status', fn (string $status): bool => str_contains($status, 'Temporary password: Tbi-'));

        $startup = Startup::query()->where('name', 'Campus Robotics')->sole();
        $this->assertSame('Maria Santos', $startup->founder);
        $this->assertSame('internal', $startup->track);
        $this->assertNull($startup->organization);
        $this->assertSame('Low-cost robotics kits for public schools.', $startup->description);
        $this->assertSame('intake-folder-1', $startup->google_drive_folder_id);

        $founder = User::query()->where('email', 'maria@example.com')->sole();
        $this->assertSame('Maria Santos', $founder->name);
        $this->assertSame('+639171234567', $founder->phone);
        $this->assertSame($startup->id, $founder->startup_id);
        $this->assertSame(User::ROLE_MEMBER_INCUBATEE, $founder->role);
        $this->assertTrue($founder->isMember());

        Mail::assertQueued(AccountProvisionedMail::class, function (AccountProvisionedMail $mail) use ($founder): bool {
            $this->assertMatchesRegularExpression('/^Tbi-\d{4}$/', $mail->temporaryPassword);
            $this->assertTrue(Hash::check($mail->temporaryPassword, $founder->password));

            return $mail->hasTo('maria@example.com');
        });

        $this->assertSame('approved', $submission->refresh()->status);
    }

    public function test_rejecting_a_pending_application_removes_it_from_the_queue(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $submission = IntakeSubmission::query()->create([
            'type' => 'startup',
            'name' => 'Rejected Venture',
            'email' => 'rejected@example.com',
            'track' => 'internal',
            'data' => ['name' => 'Rejected Venture', 'founder' => 'Rex Lim'],
        ]);

        $this->actingAs($admin)
            ->post(route('startup-intake.reject', $submission))
            ->assertRedirect(route('incubatees', ['tab' => 'pending']));

        $this->assertSame('rejected', $submission->refresh()->status);
        $this->assertDatabaseMissing('startups', ['name' => 'Rejected Venture']);
    }
}
