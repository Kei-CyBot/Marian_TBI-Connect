<?php

namespace Tests\Feature;

use App\Models\Startup;
use App\Models\StartupAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_view_contact_intake(): void
    {
        $this->actingAs($this->member())
            ->get(route('contact-intake'))
            ->assertForbidden();
    }

    public function test_member_cannot_view_archives(): void
    {
        $this->actingAs($this->member())
            ->get(route('archives'))
            ->assertForbidden();
    }

    public function test_member_cannot_view_contact_directory(): void
    {
        $this->actingAs($this->member())
            ->get(route('contacts'))
            ->assertForbidden();
    }

    public function test_admin_can_view_administrative_pages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        foreach ([route('contact-intake'), route('archives'), route('contacts')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_incubatees_page_hides_restricted_startups_from_unauthorized_users(): void
    {
        $manager = $this->user();
        $restricted = $this->restrictedStartup($manager);
        $accessible = Startup::query()->findOrFail(2);

        $this->actingAs($this->member())
            ->get(route('incubatees'))
            ->assertOk()
            ->assertViewHas('startups', fn (array $startups): bool => collect($startups)->contains(
                fn (array $startup): bool => (int) $startup['id'] === $accessible->id
            ) && ! collect($startups)->contains(
                fn (array $startup): bool => (int) $startup['id'] === $restricted->id
            ));
    }

    public function test_incubatees_page_hides_assessments_for_restricted_startups(): void
    {
        $manager = $this->user();
        $restricted = $this->restrictedStartup($manager);

        StartupAssessment::query()->create([
            'startup_id' => (string) $restricted->id,
            'startup_name' => $restricted->name,
            'competency_ratings' => ['vision' => 3],
            'other_critical_skills' => [],
            'total_points' => 3,
            'user_id' => $manager->id,
        ]);

        $this->actingAs($this->member())
            ->get(route('incubatees'))
            ->assertOk()
            ->assertViewHas('assessments', fn (array $assessments): bool => ! array_key_exists($restricted->id, $assessments));
    }

    public function test_user_cannot_save_an_assessment_for_a_restricted_startup(): void
    {
        $manager = $this->user();
        $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $restricted = $this->restrictedStartup($manager);

        $this->actingAs($otherAdmin)
            ->post(route('startup-assessments.store'), $this->assessmentPayload((string) $restricted->id, $restricted->name))
            ->assertForbidden();

        $this->assertDatabaseCount('startup_assessments', 0);
    }

    public function test_user_with_folder_access_can_save_an_assessment(): void
    {
        $manager = $this->user();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $restricted = $this->restrictedStartup($manager);
        $restricted->folderAccessUsers()->attach($admin->id, ['granted_by' => $manager->id]);

        $this->actingAs($admin)
            ->post(route('startup-assessments.store'), $this->assessmentPayload((string) $restricted->id, $restricted->name))
            ->assertRedirect(route('incubatees'));

        $this->assertDatabaseHas('startup_assessments', [
            'startup_id' => (string) $restricted->id,
            'startup_name' => $restricted->name,
            'user_id' => $admin->id,
        ]);
    }

    public function test_user_can_save_an_assessment_for_an_unrestricted_startup(): void
    {
        Startup::ensureConfigured();
        $startup = Startup::query()->findOrFail(1);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('startup-assessments.store'), $this->assessmentPayload((string) $startup->id, $startup->name))
            ->assertRedirect(route('incubatees'));

        $this->assertDatabaseHas('startup_assessments', [
            'startup_id' => (string) $startup->id,
            'startup_name' => $startup->name,
        ]);
    }

    public function test_assessment_requires_an_existing_startup(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->from(route('incubatees'))
            ->post(route('startup-assessments.store'), $this->assessmentPayload('999999', 'Missing Startup'))
            ->assertRedirect(route('incubatees'))
            ->assertSessionHasErrors('startup_id');

        $this->assertDatabaseCount('startup_assessments', 0);
    }

    private function member(): User
    {
        return User::factory()->create(['role' => User::ROLE_MEMBER_INCUBATEE]);
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    private function restrictedStartup(User $manager): Startup
    {
        Startup::ensureConfigured();
        $startup = Startup::query()->findOrFail(1);
        $startup->update([
            'folder_access_restricted' => true,
            'folder_access_manager_id' => $manager->id,
        ]);

        return $startup;
    }

    private function assessmentPayload(string $startupId, string $startupName): array
    {
        $keys = collect(config('startup_assessment'))
            ->flatMap(static fn (array $area): array => array_column($area['competencies'], 'key'));
        $ratings = $keys->mapWithKeys(static fn (string $key): array => [$key => 3])->all();

        return [
            'startup_id' => $startupId,
            'startup_name' => $startupName,
            'data' => json_encode([
                'competencyRatings' => $ratings,
                'otherCriticalSkills' => ['', '', '', '', ''],
            ], JSON_THROW_ON_ERROR),
        ];
    }
}
