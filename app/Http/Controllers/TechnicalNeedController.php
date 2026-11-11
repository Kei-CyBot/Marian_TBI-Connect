<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTechnicalNeedRequest;
use App\Mail\TechnicalNeedUpdatedAlert;
use App\Models\Startup;
use App\Models\TechnicalNeed;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class TechnicalNeedController extends Controller
{
    public function index(Request $request): View
    {
        Startup::ensureConfigured();

        $validated = $request->validate([
            'startup_id' => ['nullable', 'integer', 'exists:startups,id'],
            'sort_by' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'priority' => ['nullable', 'in:Low,Moderate,High'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
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
        $requirements = [];
        $responsibleOptions = [];

        $sortColumns = [
            'title' => 'requirement',
            'specifications' => 'description',
            'price_subscription' => 'subscription_price',
            'months_needed' => 'months_needed',
            'responsible_team_member' => 'responsible_team_member',
            'date_entry' => 'date_entry',
            'date_needed' => 'date_needed',
            'resource_tool_needed' => 'resource_tool',
            'prospect_resource_person' => 'prospect_resource_person',
            'priority' => 'priority',
            'remarks' => 'remarks',
            'status' => 'status',
        ];
        $sortBy = $validated['sort_by'] ?? 'date_entry';
        if (! array_key_exists($sortBy, $sortColumns)) {
            $sortBy = 'date_entry';
        }
        $sortOrder = ($validated['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($startupId !== null) {
            $selectedStartup = $startups->firstWhere('id', (int) $startupId);
            abort_unless($selectedStartup, 403, 'You do not have access to this incubatee folder.');
        }

        if ($selectedStartup) {
            $query = TechnicalNeed::query()
                ->with('startup:id,name', 'user:id,name', 'reviewer:id,name')
                ->where('startup_id', $selectedStartup->id);

            foreach ([
                'title' => 'requirement',
                'specifications' => 'description',
                'resource_tool_needed' => 'resource_tool',
                'prospect_resource_person' => 'prospect_resource_person',
                'remarks' => 'remarks',
            ] as $parameter => $column) {
                $value = trim((string) $request->query($parameter, ''));
                if ($value !== '') {
                    $query->where($column, 'like', '%'.$value.'%');
                }
            }

            foreach ([
                'price_min' => ['subscription_price', '>='],
                'price_max' => ['subscription_price', '<='],
                'months_min' => ['months_needed', '>='],
                'months_max' => ['months_needed', '<='],
            ] as $parameter => [$column, $operator]) {
                $value = $request->query($parameter);
                if (is_numeric($value)) {
                    $query->where($column, $operator, $value);
                }
            }

            foreach ([
                'date_entry_from' => ['date_entry', '>='],
                'date_entry_to' => ['date_entry', '<='],
                'date_needed_from' => ['date_needed', '>='],
                'date_needed_to' => ['date_needed', '<='],
            ] as $parameter => [$column, $operator]) {
                $value = $request->query($parameter);
                if (is_string($value) && $value !== '') {
                    $query->whereDate($column, $operator, $value);
                }
            }

            foreach (['responsible_team_member', 'priority', 'status'] as $parameter) {
                $value = $request->query($parameter);
                if (is_string($value) && $value !== '') {
                    $query->where($parameter, $value);
                }
            }

            $query->orderBy($sortColumns[$sortBy], $sortOrder);
            if ($sortBy !== 'date_entry') {
                $query->orderByDesc('date_entry');
            }

            $requirements = $query->get()
                ->map(static fn (TechnicalNeed $need): array => [
                    'id' => $need->id,
                    'startupId' => $need->startup_id,
                    'startupName' => $need->startup->name,
                    'requirement' => $need->requirement,
                    'description' => $need->description,
                    'price' => $need->subscription_price,
                    'months' => $need->months_needed,
                    'responsible' => $need->responsible_team_member,
                    'requestor' => $need->user?->name,
                    'dateEntry' => $need->date_entry?->format('Y-m-d'),
                    'dateNeeded' => $need->date_needed?->format('Y-m-d'),
                    'resource' => $need->resource_tool,
                    'prospect' => $need->prospect_resource_person,
                    'status' => $need->status,
                    'priority' => $need->priority,
                    'remarks' => $need->remarks,
                    'attachmentUrl' => $need->attachment_path
                        ? route('technical-needs.attachment', $need)
                        : null,
                    'attachmentName' => $need->attachment_path ? basename($need->attachment_path) : null,
                    'approvalUrl' => $need->approval_attachment_path
                        ? route('technical-needs.view-document', ['technicalNeed' => $need, 'document' => 'approval'])
                        : null,
                    'reviewNotes' => $need->review_notes,
                    'reviewedBy' => $need->reviewer?->name,
                    'reviewedAt' => $need->reviewed_at?->format('M d, Y g:i A'),
                ])
                ->all();

            $responsibleOptions = TechnicalNeed::query()
                ->where('startup_id', $selectedStartup->id)
                ->whereNotNull('responsible_team_member')
                ->distinct()
                ->orderBy('responsible_team_member')
                ->pluck('responsible_team_member')
                ->all();
        }

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
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'responsibleOptions' => $responsibleOptions,
        ]);
    }

    public function store(SaveTechnicalNeedRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $startup = Startup::query()->findOrFail($validated['startup_id']);
        abort_unless($startup->userCanAccessFolder($request->user()), 403);

        TechnicalNeed::query()->create($validated + [
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return to_route('technical-needs', ['startup_id' => $startup->id])
            ->with('technical_need_status', 'Technical need submitted for review.');
    }

    public function update(SaveTechnicalNeedRequest $request, TechnicalNeed $technicalNeed): RedirectResponse
    {
        $validated = $request->validated();
        abort_unless((int) $validated['startup_id'] === $technicalNeed->startup_id, 422);
        $startup = $technicalNeed->startup;
        abort_unless($startup && $startup->userCanAccessFolder($request->user()), 403);

        $technicalNeed->update($validated);

        $managers = User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])
            ->pluck('email')
            ->filter()
            ->all();
        if (! empty($managers)) {
            Mail::to($managers)->queue(new TechnicalNeedUpdatedAlert($technicalNeed));
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
        $paths = array_filter([
            $technicalNeed->attachment_path,
            $technicalNeed->approval_attachment_path,
        ]);
        $technicalNeed->delete();
        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }

        return to_route('technical-needs', ['startup_id' => $startupId])
            ->with('technical_need_status', 'Technical need removed.');
    }

    public function review(Request $request, TechnicalNeed $technicalNeed): RedirectResponse
    {
        abort_unless(
            $technicalNeed->startup?->userCanAccessFolder($request->user()),
            403
        );

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approved', 'rejected'])],
            'approval_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:10240'],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $attachment = $request->file('approval_document');
        $oldPath = $technicalNeed->approval_attachment_path;
        $newPath = null;
        $originalName = null;

        if ($attachment !== null) {
            $newPath = $attachment->store('technical-needs/'.$technicalNeed->startup_id.'/approvals', 'local');
            if ($newPath === false) {
                throw new RuntimeException('The technical-needs approval attachment could not be stored.');
            }
            $originalName = $attachment->getClientOriginalName();
        }

        try {
            DB::transaction(function () use ($technicalNeed, $validated, $newPath, $originalName): void {
                $technicalNeed->update([
                    'status' => $validated['action'],
                    'review_notes' => $validated['review_notes'] ?? null,
                    'reviewed_by' => request()->user()->id,
                    'reviewed_at' => now(),
                    'approval_attachment_path' => $newPath ?? $technicalNeed->approval_attachment_path,
                    'approval_attachment_original_name' => $originalName ?? $technicalNeed->approval_attachment_original_name,
                ]);
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

        ActivityLogger::log(
            'technical_need_reviewed',
            'Technical need '.$technicalNeed->requirement.' was '.$validated['action'],
            $technicalNeed,
            ['status' => $validated['action'], 'review_notes' => $validated['review_notes'] ?? null]
        );

        return to_route('technical-needs', ['startup_id' => $technicalNeed->startup_id])
            ->with('technical_need_status', 'Technical need '.$validated['action'].'.');
    }

    public function viewDocument(Request $request, TechnicalNeed $technicalNeed)
    {
        abort_unless(
            $technicalNeed->startup?->userCanAccessFolder($request->user()),
            403
        );

        $path = $request->query('document') === 'request'
            ? $technicalNeed->attachment_path
            : $technicalNeed->approval_attachment_path;
        abort_unless($path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Disposition' => 'inline',
        ]);
    }

    public function downloadAttachment(TechnicalNeed $technicalNeed)
    {
        return $this->downloadStoredFile($technicalNeed, $technicalNeed->attachment_path);
    }

    public function downloadApproval(TechnicalNeed $technicalNeed)
    {
        return $this->downloadStoredFile(
            $technicalNeed,
            $technicalNeed->approval_attachment_path,
            $technicalNeed->approval_attachment_original_name
        );
    }

    private function downloadStoredFile(TechnicalNeed $technicalNeed, ?string $path, ?string $downloadName = null)
    {
        abort_unless($path, 404);
        abort_unless(
            $technicalNeed->startup?->userCanAccessFolder(request()->user()),
            403
        );

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->download($disk->path($path), $downloadName ?? basename($path));
    }
}
