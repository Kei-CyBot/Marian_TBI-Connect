<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\TechnicalNeed;
use App\Services\TechnicalNeedMetrics;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index', $this->reportData());
    }

    public function exportCsv(): StreamedResponse
    {
        $data = $this->reportData();
        $filename = 'tbi_dashboard_report_'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data): void {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, ['Executive Metrics']);
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Export Date', now()->toDateTimeString()]);
            fputcsv($output, ['Total Uploaded Files', $data['technicalNeedFiles'] + $data['repositoryFiles']]);
            fputcsv($output, ['Technical Needs Files', $data['technicalNeedFiles']]);
            fputcsv($output, ['Document Repository Files', $data['repositoryFiles']]);
            fputcsv($output, ['Total Technical Needs', $data['technicalNeeds']->count()]);
            fputcsv($output, ['Pending Technical Needs', $data['technicalNeeds']->where('status', 'pending')->count()]);
            fputcsv($output, ['Approved Technical Needs', $data['technicalNeeds']->where('status', 'approved')->count()]);
            fputcsv($output, ['Total Allocated Items', $data['allocatedItems']]);
            fputcsv($output, ['Total Estimated Cost (PHP)', number_format($data['estimatedCost'], 2, '.', '')]);
            fputcsv($output, []);

            fputcsv($output, ['Priority Breakdown']);
            fputcsv($output, ['Priority', 'Count']);
            foreach (['Low', 'Moderate', 'High'] as $priority) {
                fputcsv($output, [$priority, $data['priorities'][$priority]]);
            }
            fputcsv($output, []);

            fputcsv($output, ['Itemized Technical Needs']);
            fputcsv($output, [
                'ID',
                'Startup/Incubatee Name',
                'Title/Requirement',
                'Priority',
                'Status',
                'Responsible Person',
                'Resource/Tool',
                'Estimated Cost (PHP)',
                'Date Entry',
            ]);
            foreach ($data['technicalNeeds'] as $need) {
                fputcsv($output, [
                    $need->getKey(),
                    $need->startup?->name ?? '',
                    $need->requirement,
                    $need->priority,
                    $need->status,
                    $need->responsible_team_member ?? '',
                    $need->resource_tool ?? '',
                    $need->subscription_price !== null
                        ? number_format((float) $need->subscription_price, 2, '.', '')
                        : '',
                    $need->date_entry?->format('Y-m-d') ?? '',
                ]);
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function reportData(): array
    {
        $metrics = TechnicalNeedMetrics::summary();
        $repositoryFiles = FormSubmission::query()
            ->where(static fn ($query) => $query->whereNotNull('local_path')->orWhereNotNull('google_drive_file_id'))
            ->count();
        $technicalNeeds = TechnicalNeed::query()
            ->with('startup:id,name')
            ->orderByDesc('created_at')
            ->get();

        $auditRows = collect()
            ->merge(
                $technicalNeeds
                    ->filter(static fn (TechnicalNeed $need): bool => $need->attachment_path
                        || $need->approval_attachment_path)
                    ->map(static fn (TechnicalNeed $need): array => [
                        'document' => $need->requirement,
                        'source' => 'Technical Need',
                        'owner' => $need->startup?->name ?? '—',
                        'status' => ucfirst((string) $need->status),
                        'timestamp' => $need->updated_at?->timestamp ?? 0,
                        'updated_at' => $need->updated_at?->format('M j, Y') ?? '—',
                    ])
            )
            ->merge(
                FormSubmission::query()
                    ->with('user:id,name')
                    ->where(static fn ($query) => $query->whereNotNull('local_path')->orWhereNotNull('google_drive_file_id'))
                    ->get()
                    ->map(static fn (FormSubmission $submission): array => [
                        'document' => $submission->form_name ?: ($submission->prerequisite_original_name ?: 'Form submission'),
                        'source' => 'Form Repository',
                        'owner' => $submission->startup_name ?: ($submission->user?->name ?? '—'),
                        'status' => $submission->approval_status
                            ? ucfirst(str_replace('_', ' ', $submission->approval_status->value))
                            : '—',
                        'timestamp' => ($submission->drive_synced_at ?? $submission->updated_at)?->timestamp ?? 0,
                        'updated_at' => ($submission->drive_synced_at ?? $submission->updated_at)?->format('M j, Y') ?? '—',
                    ])
            )
            ->sortByDesc('timestamp')
            ->values();

        return [
            'technicalNeedFiles' => $metrics['fileCount'],
            'repositoryFiles' => $repositoryFiles,
            'priorities' => $metrics['priorities'],
            'technicalNeeds' => $technicalNeeds,
            'allocatedItems' => $metrics['allocatedItems'],
            'estimatedCost' => $metrics['estimatedCost'],
            'auditRows' => $auditRows,
        ];
    }
}
