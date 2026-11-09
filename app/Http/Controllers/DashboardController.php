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
use App\Models\TechnicalNeed;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $technicalNeedFiles = TechnicalNeed::query()->whereNotNull('attachment_path')->count()
            + TechnicalNeed::query()->whereNotNull('approval_attachment_path')->count()
            + TechnicalNeed::query()->whereNotNull('approval_document_path')->count();
        $repositoryFiles = FormSubmission::query()
            ->where(static fn ($query) => $query->whereNotNull('local_path')->orWhereNotNull('google_drive_file_id'))
            ->count();
        $technicalNeedPriorities = TechnicalNeed::query()
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

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
                    'total' => $technicalNeedFiles + $repositoryFiles,
                    'technicalNeeds' => $technicalNeedFiles,
                    'repository' => $repositoryFiles,
                ],
                'technicalNeeds' => [
                    'total' => (int) $technicalNeedPriorities->sum(),
                    'pending' => TechnicalNeed::query()->where('status', 'pending')->count(),
                    'priorities' => [
                        'Low' => (int) ($technicalNeedPriorities['Low'] ?? 0),
                        'Moderate' => (int) ($technicalNeedPriorities['Moderate'] ?? 0),
                        'High' => (int) ($technicalNeedPriorities['High'] ?? 0),
                    ],
                ],
                'resourceAllocations' => [
                    'total' => TechnicalNeed::query()->whereNotNull('resource_tool')->where('resource_tool', '!=', '')->count(),
                    'estimatedCost' => (float) TechnicalNeed::query()->sum('subscription_price'),
                ],
                'recentActivity' => ActivityLog::query()->with('user:id,name')->latest('id')->limit(5)->get(),
            ],
            'pipeline' => $pipeline,
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'dashboard_analytics_report_'.now()->format('Y-m-d').'.csv';

        $technicalNeedFiles = TechnicalNeed::query()->whereNotNull('attachment_path')->count()
            + TechnicalNeed::query()->whereNotNull('approval_attachment_path')->count()
            + TechnicalNeed::query()->whereNotNull('approval_document_path')->count();
        $repositoryFiles = FormSubmission::query()
            ->where(static fn ($query) => $query->whereNotNull('local_path')->orWhereNotNull('google_drive_file_id'))
            ->count();
        $priorityRows = TechnicalNeed::query()
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');
        $allocatedItems = TechnicalNeed::query()
            ->whereNotNull('resource_tool')
            ->where('resource_tool', '!=', '')
            ->count();
        $estimatedCost = (float) TechnicalNeed::query()->sum('subscription_price');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use (
            $technicalNeedFiles,
            $repositoryFiles,
            $priorityRows,
            $allocatedItems,
            $estimatedCost
        ): void {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, ['Executive Summary']);
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Export Date', now()->toDateTimeString()]);
            fputcsv($output, ['Total Uploaded Files', $technicalNeedFiles + $repositoryFiles]);
            fputcsv($output, ['Technical Needs Files', $technicalNeedFiles]);
            fputcsv($output, ['Document Repository Files', $repositoryFiles]);
            fputcsv($output, ['Total Startups', Startup::query()->count()]);
            fputcsv($output, ['Total Contacts', Contact::query()->count()]);
            fputcsv($output, []);

            fputcsv($output, ['Technical Needs Priority Breakdown']);
            fputcsv($output, ['Priority', 'Count']);
            foreach (['Low', 'Moderate', 'High'] as $priority) {
                fputcsv($output, [$priority, (int) ($priorityRows[$priority] ?? 0)]);
            }
            fputcsv($output, []);

            fputcsv($output, ['Financial & Resource Allocations']);
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Total Allocated Items', $allocatedItems]);
            fputcsv($output, ['Total Estimated Cost (PHP)', number_format($estimatedCost, 2, '.', '')]);
            fputcsv($output, []);

            fputcsv($output, ['Active Technical Requirements List']);
            fputcsv($output, [
                'ID',
                'Startup/Incubatee Name',
                'Title/Requirement',
                'Priority',
                'Status',
                'Responsible Person',
                'Estimated Cost (PHP)',
                'Date Entry',
            ]);
            TechnicalNeed::query()
                ->with('startup:id,name')
                ->orderByDesc('created_at')
                ->chunk(100, function ($needs) use ($output): void {
                    foreach ($needs as $need) {
                        fputcsv($output, [
                            $need->getKey(),
                            $need->startup?->name ?? '',
                            $need->requirement,
                            $need->priority,
                            $need->status,
                            $need->responsible_team_member ?? '',
                            $need->subscription_price !== null
                                ? number_format((float) $need->subscription_price, 2, '.', '')
                                : '',
                            $need->created_at?->format('Y-m-d') ?? '',
                        ]);
                    }
                });

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
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
