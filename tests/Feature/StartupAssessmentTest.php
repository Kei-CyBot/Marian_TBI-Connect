<?php

namespace Tests\Feature;

use App\Models\StartupAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartupAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_incubatees_page_loads_assessment_feature_for_each_startup(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('incubatees'))
            ->assertOk()
            ->assertSee('open-startup-assessment')
            ->assertSee('competencyRatings')
            ->assertSee('Save Assessment');
    }

    public function test_authenticated_user_can_save_and_update_startup_assessment(): void
    {
        $user = User::factory()->create();
        $keys = collect(config('startup_assessment'))
            ->flatMap(static fn (array $area): array => array_column($area['competencies'], 'key'));
        $ratings = $keys->mapWithKeys(static fn (string $key): array => [$key => 4])->all();

        $payload = [
            'startup_id' => '1',
            'startup_name' => 'AgriSense Labs',
            'data' => json_encode([
                'competencyRatings' => $ratings,
                'otherCriticalSkills' => ['Clinical validation', '', '', '', ''],
            ], JSON_THROW_ON_ERROR),
        ];

        $this->actingAs($user)
            ->post(route('startup-assessments.store'), $payload)
            ->assertRedirect(route('incubatees'))
            ->assertSessionHas('assessment_status');

        $assessment = StartupAssessment::query()->firstOrFail();
        $this->assertSame('1', $assessment->startup_id);
        $this->assertSame(4 * $keys->count(), $assessment->total_points);
        $this->assertSame(['Clinical validation'], $assessment->other_critical_skills);
        $this->assertSame($user->id, $assessment->user_id);

        $ratings[$keys->first()] = 5;
        $this->actingAs($user)->post(route('startup-assessments.store'), [
            ...$payload,
            'data' => json_encode([
                'competencyRatings' => $ratings,
                'otherCriticalSkills' => ['', '', '', '', ''],
            ], JSON_THROW_ON_ERROR),
        ])->assertRedirect(route('incubatees'));

        $this->assertDatabaseCount('startup_assessments', 1);
        $this->assertSame(4 * $keys->count() + 1, $assessment->refresh()->total_points);
    }

    public function test_assessment_rejects_ratings_outside_the_source_scale(): void
    {
        $keys = collect(config('startup_assessment'))
            ->flatMap(static fn (array $area): array => array_column($area['competencies'], 'key'));
        $ratings = $keys->mapWithKeys(static fn (string $key): array => [$key => 3])->all();
        $ratings[$keys->first()] = 6;

        $this->actingAs(User::factory()->create())
            ->from(route('incubatees'))
            ->post(route('startup-assessments.store'), [
                'startup_id' => '1',
                'startup_name' => 'AgriSense Labs',
                'data' => json_encode([
                    'competencyRatings' => $ratings,
                    'otherCriticalSkills' => ['', '', '', '', ''],
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertSessionHasErrors('data.competencyRatings.'.$keys->first());

        $this->assertDatabaseCount('startup_assessments', 0);
    }

    public function test_guest_cannot_save_assessment(): void
    {
        $this->post(route('startup-assessments.store'), [])
            ->assertRedirect(route('login'));
    }
}
