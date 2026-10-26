<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\IntakeSubmission;
use App\Models\Startup;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $activeStartupTracks = Startup::query()
            ->selectRaw('track, count(*) as total')
            ->groupBy('track')
            ->pluck('total', 'track');
        $verifiedContactSides = Contact::query()
            ->where('verification_status', 'Verified Contact')
            ->selectRaw('side, count(*) as total')
            ->groupBy('side')
            ->pluck('total', 'side');
        $graduateTracks = Startup::query()
            ->whereIn('status', ['Graduate', 'Alumni Network'])
            ->selectRaw('track, count(*) as total')
            ->groupBy('track')
            ->pluck('total', 'track');
        $pendingContacts = ContactRegistrationApplication::query()
            ->where('status', 'Pending')
            ->selectRaw('stream, count(*) as total')
            ->groupBy('stream')
            ->pluck('total', 'stream');
        $pendingIntake = IntakeSubmission::query()
            ->where('status', 'Pending')
            ->selectRaw('track, count(*) as total')
            ->groupBy('track')
            ->pluck('total', 'track');

        $statuses = ['Applicant', 'Incubatee', 'Incubatee Extended', 'Graduate', 'Alumni Network'];
        $startupStatuses = Startup::query()
            ->selectRaw('status, track, count(*) as total')
            ->groupBy('status', 'track')
            ->get();
        $pipeline = collect($statuses)->map(static function (string $status) use ($startupStatuses): array {
            $rows = $startupStatuses->where('status', $status);

            return [
                'status' => $status,
                'all' => (int) $rows->sum('total'),
                'internal' => (int) $rows->where('track', 'internal')->sum('total'),
                'external' => (int) $rows->where('track', 'external')->sum('total'),
            ];
        })->all();

        return view('dashboard', [
            'metrics' => [
                'activeStartups' => $this->trackCounts($activeStartupTracks),
                'verifiedContacts' => $this->trackCounts($verifiedContactSides),
                'graduates' => $this->trackCounts($graduateTracks),
                'pendingIntakeApplications' => [
                    'all' => (int) $pendingContacts->sum() + (int) $pendingIntake->sum(),
                    'internal' => (int) ($pendingContacts['internal'] ?? 0) + (int) ($pendingIntake['internal'] ?? 0),
                    'external' => (int) ($pendingContacts['external'] ?? 0) + (int) ($pendingIntake['external'] ?? 0),
                ],
                'recentActivity' => ActivityLog::query()->latest()->limit(5)->get(),
            ],
            'pipeline' => $pipeline,
        ]);
    }

    private function trackCounts($counts): array
    {
        return [
            'all' => (int) $counts->sum(),
            'internal' => (int) ($counts['internal'] ?? 0),
            'external' => (int) ($counts['external'] ?? 0),
        ];
    }
}
