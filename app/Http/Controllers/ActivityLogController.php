<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->canManageRecords(), 403);

        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.trim((string) $request->string('search')).'%';
                $query->where(fn ($query) => $query
                    ->where('description', 'like', $search)
                    ->orWhere('action', 'like', $search));
            })
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('activity-logs.index', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'lastId' => $logs->getCollection()->max('id') ?? 0,
        ]);
    }

    public function stream(Request $request): JsonResponse
    {
        abort_unless($request->user()?->canManageRecords(), 403);

        $afterId = max(0, $request->integer('after_id'));

        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(static fn (ActivityLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'user' => $log->user?->name ?? 'System',
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
                'created_at_human' => $log->created_at?->diffForHumans(),
            ]);

        return response()->json($logs);
    }
}
