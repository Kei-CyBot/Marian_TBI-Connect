<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->canManageRecords(), 403);

        $logs = ActivityLog::query()
            ->with('user')
            ->latest()
            ->paginate(50);

        return view('activity-logs.index', [
            'logs' => $logs,
        ]);
    }
}
