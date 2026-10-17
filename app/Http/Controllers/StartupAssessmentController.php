<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStartupAssessmentRequest;
use App\Models\Startup;
use App\Models\StartupAssessment;
use App\Models\StartupDriveLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StartupAssessmentController extends Controller
{
    public function index(): View
    {
        Startup::ensureConfigured();

        $assessments = StartupAssessment::query()
            ->get()
            ->keyBy('startup_id')
            ->map(static fn (StartupAssessment $assessment): array => [
                'ratings' => $assessment->competency_ratings,
                'otherCriticalSkills' => $assessment->other_critical_skills,
                'totalPoints' => $assessment->total_points,
                'updatedAt' => $assessment->updated_at?->toIso8601String(),
            ])
            ->all();

        $defaultRatings = collect(config('startup_assessment', []))
            ->flatMap(static fn (array $area): array => array_fill_keys(
                array_column($area['competencies'], 'key'),
                3
            ))
            ->all();

        $accessibleStartupIds = Startup::query()
            ->with('folderAccessUsers:id')
            ->get()
            ->filter(fn (Startup $startup): bool => $startup->userCanAccessFolder(request()->user()))
            ->modelKeys();

        $driveLinks = StartupDriveLink::query()
            ->whereIn('startup_id', $accessibleStartupIds)
            ->get()
            ->groupBy('startup_id')
            ->map(static fn ($links) => $links->map(static fn (StartupDriveLink $link): array => [
                'id' => $link->id,
                'label' => $link->label,
                'url' => $link->url,
            ])->values()->all())
            ->all();

        $startupRecord = static fn (Startup $startup): array => [
            'id' => $startup->id,
            'name' => $startup->name,
            'founder' => $startup->founder,
            'email' => $startup->contact_email,
            'track' => $startup->track,
            'ventureType' => $startup->venture_type,
            'status' => $startup->status,
            'cohortId' => $startup->cohort_id,
            'campusDepartment' => $startup->campus_department,
            'legalEntityType' => $startup->legal_entity_type,
        ];
        $startups = Startup::query()->orderBy('id')->get()->map($startupRecord)->all();

        return view('incubatees', [
            'startups' => $startups,
            'assessments' => $assessments,
            'assessmentAreas' => config('startup_assessment', []),
            'assessmentDefaults' => $defaultRatings,
            'driveLinks' => $driveLinks,
        ]);
    }

    public function store(SaveStartupAssessmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $ratings = $validated['data']['competencyRatings'];
        $skills = array_values(array_filter(
            $validated['data']['otherCriticalSkills'],
            static fn (?string $skill): bool => filled($skill)
        ));

        StartupAssessment::query()->updateOrCreate(
            ['startup_id' => $validated['startup_id']],
            [
                'startup_name' => $validated['startup_name'],
                'competency_ratings' => $ratings,
                'other_critical_skills' => $skills,
                'total_points' => array_sum($ratings),
                'user_id' => $request->user()->id,
            ]
        );

        return to_route('incubatees')->with('assessment_status', 'Assessment saved successfully.');
    }
}
