<?php

namespace Tests\Feature;

use App\Models\FormSubmission;
use App\Models\Startup;
use App\Models\StartupDriveLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_renaming_a_startup_propagates_to_submissions_and_drive_links(): void
    {
        $admin = $this->admin();
        $startup = $this->startup(['name' => 'Old Name Co']);

        $submission = FormSubmission::query()->create([
            'user_id' => $admin->id,
            'form_id' => 1,
            'form_name' => 'Startup Profile',
            'startup_name' => 'Old Name Co',
            'track' => 'external',
            'data' => [],
            'approval_status' => 'submitted',
        ]);

        $driveLink = StartupDriveLink::query()->create([
            'startup_id' => (string) $startup->id,
            'startup_name' => 'Old Name Co',
            'label' => 'Pitch deck',
            'url' => 'https://drive.google.com/drive/folders/abc123',
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('incubatees.update', $startup), $this->startupPayload(['name' => 'New Name Co']))
            ->assertRedirect(route('incubatees'));

        $this->assertDatabaseHas('form_submissions', [
            'id' => $submission->id,
            'startup_name' => 'New Name Co',
        ]);
        $this->assertDatabaseHas('startup_drive_links', [
            'id' => $driveLink->id,
            'startup_name' => 'New Name Co',
        ]);
    }

    public function test_duplicate_startup_names_are_rejected(): void
    {
        $admin = $this->admin();
        $this->startup(['name' => 'First Co']);

        $this->actingAs($admin)
            ->from(route('incubatees'))
            ->post(route('incubatees.store'), $this->startupPayload(['name' => 'First Co']))
            ->assertRedirect(route('incubatees'))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Startup::query()->count());
    }

    public function test_startup_can_keep_its_own_name_when_updated(): void
    {
        $admin = $this->admin();
        $startup = $this->startup(['name' => 'Same Co']);

        $this->actingAs($admin)
            ->put(route('incubatees.update', $startup), $this->startupPayload(['name' => 'Same Co']))
            ->assertRedirect(route('incubatees'))
            ->assertSessionHasNoErrors();
    }

    public function test_archiving_a_startup_records_the_provided_reason(): void
    {
        $admin = $this->admin();
        $startup = $this->startup();

        $this->actingAs($admin)
            ->delete(route('incubatees.destroy', $startup), ['archive_reason' => 'Graduated from the program'])
            ->assertRedirect(route('incubatees'));

        $this->assertSoftDeleted('startups', ['id' => $startup->id]);
        $this->assertDatabaseHas('startups', [
            'id' => $startup->id,
            'archive_reason' => 'Graduated from the program',
        ]);
    }

    public function test_file_manager_excludes_submissions_and_drive_links_without_an_active_startup(): void
    {
        $admin = $this->admin();
        $active = $this->startup(['name' => 'Active Co']);
        $archived = $this->startup(['name' => 'Archived Co']);

        $activeSubmission = $this->submission($admin, 'Active Co');
        $archivedSubmission = $this->submission($admin, 'Archived Co');
        $orphanSubmission = $this->submission($admin, 'Ghost Co');

        $activeLink = $this->driveLink($admin, $active, 'Active Co');
        $archivedLink = $this->driveLink($admin, $archived, 'Archived Co');
        $orphanLink = $this->driveLink($admin, null, 'Ghost Co');

        $archived->delete();

        $response = $this->actingAs($admin)->get(route('files'))->assertOk();

        $response->assertViewHas('savedSubmissions', fn (array $submissions): bool => collect($submissions)->pluck('submissionId')->all() === [$activeSubmission->id]
            && ! collect($submissions)->pluck('submissionId')->contains($archivedSubmission->id)
            && ! collect($submissions)->pluck('submissionId')->contains($orphanSubmission->id));

        $response->assertViewHas('driveLinks', fn (array $links): bool => collect($links)->pluck('id')->contains($activeLink->id)
            && ! collect($links)->pluck('id')->contains($archivedLink->id)
            && ! collect($links)->pluck('id')->contains($orphanLink->id));
    }

    public function test_demo_records_are_not_seeded_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        Startup::ensureConfigured();
        $this->assertDatabaseCount('startups', 0);

        $this->actingAs($this->admin())
            ->get(route('contacts'))
            ->assertOk();

        $this->assertDatabaseCount('contacts', 0);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function startup(array $attributes = []): Startup
    {
        return Startup::query()->create([
            'name' => 'Demo Co',
            'founder' => 'Demo Founder',
            'contact_email' => 'founder@example.test',
            'track' => 'external',
            'status' => 'Applicant',
            'cohort_id' => 1,
            ...$attributes,
        ]);
    }

    private function startupPayload(array $attributes = []): array
    {
        return [
            'name' => 'Demo Co',
            'founder' => 'Demo Founder',
            'email' => 'founder@example.test',
            'track' => 'external',
            'status' => 'Applicant',
            'cohort_id' => 1,
            ...$attributes,
        ];
    }

    private function submission(User $user, string $startupName): FormSubmission
    {
        return FormSubmission::query()->create([
            'user_id' => $user->id,
            'form_id' => 1,
            'form_name' => 'Startup Profile',
            'startup_name' => $startupName,
            'track' => 'external',
            'data' => [],
            'approval_status' => 'submitted',
        ]);
    }

    private function driveLink(User $user, ?Startup $startup, string $startupName): StartupDriveLink
    {
        return StartupDriveLink::query()->create([
            'startup_id' => (string) $startup?->id,
            'startup_name' => $startupName,
            'label' => 'Pitch deck',
            'url' => 'https://drive.google.com/drive/folders/'.strtolower(str_replace(' ', '-', $startupName)),
            'user_id' => $user->id,
        ]);
    }
}
