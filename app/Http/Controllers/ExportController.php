<?php

namespace App\Http\Controllers;

use App\Helpers\FormFormatHelper;
use App\Models\FormSubmission;
use App\Models\TechnicalNeed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response; // keep for compatibility
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function exportSubmissions(Request $request)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN], true), 403);

        $filename = 'form-submissions-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function (): void {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, [
                'ID',
                'Form ID',
                'Form Name',
                'Startup',
                'Track',
                'Submitted By',
                'Status',
                'Submitted At',
                'Signer Name',
                'Response Data',
            ]);

            FormSubmission::query()
                ->with('user')
                ->latest()
                ->chunk(100, function ($submissions) use ($output): void {
                    foreach ($submissions as $submission) {
                        $data = is_array($submission->data) ? $submission->data : [];
                        $flattened = [];
                        foreach ($data as $key => $value) {
                            if ($key === 'signer_name') {
                                continue;
                            }
                            $flattened[] = FormFormatHelper::toLabel((string) $key).': '.FormFormatHelper::formatScalar($value);
                        }

                        fputcsv($output, [
                            $submission->getKey(),
                            $submission->form_id,
                            $submission->form_name,
                            $submission->startup_name,
                            $submission->track,
                            $submission->user?->name ?? '',
                            is_object($submission->approval_status)
                                ? ($submission->approval_status->value ?? (string) $submission->approval_status)
                                : (string) $submission->approval_status,
                            $submission->created_at?->toDateTimeString() ?? '',
                            $data['signer_name'] ?? '',
                            implode(' | ', $flattened),
                        ]);
                    }
                });

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportTechnicalNeeds(Request $request)
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SUPER_ADMIN], true), 403);

        $filename = 'technical-needs-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function (): void {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, [
                'ID',
                'Startup',
                'Requirement',
                'Description',
                'Subscription Price',
                'Months Needed',
                'Responsible Team Member',
                'Date Entry',
                'Date Needed',
                'Resource/Tool',
                'Prospect Resource Person',
                'Status',
                'Priority',
                'Remarks',
            ]);

            TechnicalNeed::query()
                ->with('startup')
                ->latest('date_entry')
                ->chunk(100, function ($needs) use ($output): void {
                    foreach ($needs as $need) {
                        fputcsv($output, [
                            $need->getKey(),
                            $need->startup?->name ?? '',
                            $need->requirement,
                            $need->description ?? '',
                            $need->subscription_price ?? '',
                            $need->months_needed ?? '',
                            $need->responsible_team_member ?? '',
                            $need->date_entry?->format('Y-m-d') ?? '',
                            $need->date_needed?->format('Y-m-d') ?? '',
                            $need->resource_tool ?? '',
                            $need->prospect_resource_person ?? '',
                            $need->status,
                            $need->priority,
                            $need->remarks ?? '',
                        ]);
                    }
                });

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }
}
