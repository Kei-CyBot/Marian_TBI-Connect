<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTechnicalNeedRequest;
use App\Models\Startup;
use App\Models\TechnicalNeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use Illuminate\View\View;

class TechnicalNeedController extends Controller
{
    public function index(Request $request): View
    {
        Startup::ensureConfigured();

        $validated = $request->validate([
            'startup_id' => ['nullable', 'integer', 'exists:startups,id'],
        ]);
        $startupId = $validated['startup_id'] ?? null;
        $user = $request->user();
        $startups = Startup::query()
            ->with('folderAccessUsers:id')
            ->withCount('technicalNeeds')
            ->orderBy('name')
            ->get()
            ->filter(static fn (Startup $startup): bool => $startup->userCanAccessFolder($user))
            ->values();
        $selectedStartup = null;

        if ($startupId !== null) {
            $selectedStartup = $startups->firstWhere('id', (int) $startupId);
            abort_unless($selectedStartup, 403, 'You do not have access to this incubatee folder.');
        }

        $requirements = $selectedStartup
            ? TechnicalNeed::query()
                ->with('startup:id,name')
                ->where('startup_id', $selectedStartup->id)
                ->latest('date_entry')
                ->get()
                ->map(static fn (TechnicalNeed $need): array => [
                    'id' => $need->id,
                    'startupId' => $need->startup_id,
                    'startupName' => $need->startup->name,
                    'requirement' => $need->requirement,
                    'description' => $need->description,
                    'price' => $need->subscription_price,
                    'months' => $need->months_needed,
                    'responsible' => $need->responsible_team_member,
                    'dateEntry' => $need->date_entry?->format('Y-m-d'),
                    'dateNeeded' => $need->date_needed?->format('Y-m-d'),
                    'resource' => $need->resource_tool,
                    'prospect' => $need->prospect_resource_person,
                    'status' => $need->status,
                    'priority' => $need->priority,
                    'remarks' => $need->remarks,
                    'approvalUrl' => $need->approval_document_path
                        ? route('technical-needs.approval', $need)
                        : null,
                ])
                ->all()
            : [];

        return view('technical-needs', [
            'requirements' => $requirements,
            'startups' => $startups
                ->map(static fn (Startup $startup): array => [
                    'id' => $startup->id,
                    'name' => $startup->name,
                    'track' => $startup->track,
                    'technicalNeedCount' => $startup->technical_needs_count,
                ])
                ->all(),
            'selectedStartup' => $selectedStartup,
        ]);
    }

    public function store(SaveTechnicalNeedRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $startup = Startup::query()->findOrFail($validated['startup_id']);
        abort_unless($startup->userCanAccessFolder($request->user()), 403);
        $approval = $request->file('signed_approval');
        $path = $approval->store('technical-needs/'.$startup->id, 'local');
        if ($path === false) {
            throw new RuntimeException('The signed technical-needs approval could not be stored.');
        }

        try {
            DB::transaction(fn () => TechnicalNeed::query()->create(
                collect($validated)->except('signed_approval')->all() + [
                    'approval_document_path' => $path,
                    'approval_document_original_name' => $approval->getClientOriginalName(),
                ]
            ));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return to_route('technical-needs', ['startup_id' => $startup->id])
            ->with('technical_need_status', 'Technical need saved with its signed approval.');
    }

    public function update(SaveTechnicalNeedRequest $request, TechnicalNeed $technicalNeed): RedirectResponse
    {
        $validated = $request->validated();
        abort_unless((int) $validated['startup_id'] === $technicalNeed->startup_id, 422);
        $startup = $technicalNeed->startup;
        abort_unless($startup && $startup->userCanAccessFolder($request->user()), 403);
        $approval = $request->file('signed_approval');
        $oldPath = $technicalNeed->approval_document_path;
        $newPath = null;

        if ($approval !== null) {
            $newPath = $approval->store('technical-needs/'.$startup->id, 'local');
            if ($newPath === false) {
                throw new RuntimeException('The signed technical-needs approval could not be stored.');
            }
        }

        try {
            DB::transaction(function () use ($technicalNeed, $validated, $approval, $newPath): void {
                $attributes = collect($validated)->except('signed_approval')->all();

                if ($approval !== null) {
                    $attributes['approval_document_path'] = $newPath;
                    $attributes['approval_document_original_name'] = $approval->getClientOriginalName();
                }

                $technicalNeed->update($attributes);
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('local')->delete($newPath);
            }

            throw $exception;
        }

        if ($newPath !== null && $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return to_route('technical-needs', ['startup_id' => $startup->id])
            ->with('technical_need_status', 'Technical need updated.');
    }

    public function destroy(TechnicalNeed $technicalNeed): RedirectResponse
    {
        abort_unless(
            $technicalNeed->startup?->userCanAccessFolder(request()->user()),
            403
        );
        $startupId = $technicalNeed->startup_id;
        $approvalPath = $technicalNeed->approval_document_path;
        $technicalNeed->delete();
        if ($approvalPath) {
            Storage::disk('local')->delete($approvalPath);
        }

        return to_route('technical-needs', ['startup_id' => $startupId])
            ->with('technical_need_status', 'Technical need removed.');
    }

    public function downloadApproval(TechnicalNeed $technicalNeed)
    {
        abort_unless($technicalNeed->approval_document_path, 404);
        abort_unless(
            $technicalNeed->startup?->userCanAccessFolder(request()->user()),
            403
        );

        $disk = Storage::disk('local');
        abort_unless($disk->exists($technicalNeed->approval_document_path), 404);

        return response()->download(
            $disk->path($technicalNeed->approval_document_path),
            $technicalNeed->approval_document_original_name ?? basename($technicalNeed->approval_document_path)
        );
    }
}
