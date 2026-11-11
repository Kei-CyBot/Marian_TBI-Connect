<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\ContactRegistrationApplication;
use App\Models\Event;
use App\Models\EventInvitation;
use App\Models\FormSubmission;
use App\Models\IntakeSubmission;
use App\Models\Startup;
use App\Services\TechnicalNeedMetrics;
use Illuminate\Http\RedirectResponse;
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

        $networkContactSides = Contact::query()
            ->selectRaw('side, count(*) as total')
            ->groupBy('side')
            ->pluck('total', 'side');

        $upcomingEvents = Event::query()
            ->where('ends_at', '>=', now())
            ->selectRaw('scope, count(*) as total')
            ->groupBy('scope')
            ->pluck('total', 'scope');

        $upcomingEventIds = Event::query()
            ->where('ends_at', '>=', now())
            ->pluck('id');

        $rsvpCounts = EventInvitation::query()
            ->whereIn('event_id', $upcomingEventIds)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = ['Applicant', 'Incubatee', 'Incubatee Extended', 'Graduate', 'Alumni Network'];
        $startupStatuses = Startup::query()
            ->selectRaw('status, track, count(*) as total')
            ->groupBy('status', 'track')
            ->get();
        $metrics = TechnicalNeedMetrics::summary();
        $repositoryFiles = FormSubmission::query()
            ->where(static fn ($query) => $query->whereNotNull('local_path')->orWhereNotNull('google_drive_file_id'))
            ->count();

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
                'networkContacts' => $this->trackCounts($networkContactSides),
                'upcomingEvents' => $this->trackCounts($upcomingEvents),
                'rsvps' => [
                    'attending' => (int) ($rsvpCounts['attending'] ?? 0),
                    'pending' => (int) ($rsvpCounts['pending'] ?? 0),
                    'declined' => (int) ($rsvpCounts['declined'] ?? 0),
                ],
                'graduates' => $this->trackCounts($graduateTracks),
                'pendingIntakeApplications' => [
                    'all' => (int) $pendingContacts->sum() + (int) $pendingIntake->sum(),
                    'internal' => (int) ($pendingContacts['internal'] ?? 0) + (int) ($pendingIntake['internal'] ?? 0),
                    'external' => (int) ($pendingContacts['external'] ?? 0) + (int) ($pendingIntake['external'] ?? 0),
                ],
                'uploadedFiles' => [
                    'total' => $metrics['fileCount'] + $repositoryFiles,
                    'technicalNeeds' => $metrics['fileCount'],
                    'repository' => $repositoryFiles,
                ],
                'technicalNeeds' => [
                    'total' => array_sum($metrics['priorities']),
                    'pending' => $metrics['pendingCount'],
                    'priorities' => $metrics['priorities'],
                ],
                'resourceAllocations' => [
                    'total' => $metrics['allocatedItems'],
                    'estimatedCost' => $metrics['estimatedCost'],
                ],
                'recentActivity' => ActivityLog::query()->with('user:id,name')->latest('id')->limit(5)->get(),
            ],
            'pipeline' => $pipeline,
        ]);
    }

    public function exportCsv(): RedirectResponse
    {
        return redirect()->to(route('reports.export-csv'));
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
