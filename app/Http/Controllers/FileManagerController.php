<?php

namespace App\Http\Controllers;

use App\Enums\Forms\ApprovalStatus;
use App\Models\FormSubmission;
use App\Models\GoogleDriveConnection;
use App\Models\Startup;
use App\Models\StartupDriveLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FileManagerController extends Controller
{
    public function index(Request $request): View
    {
        Startup::ensureConfigured();

        $startups = Startup::withTrashed()
            ->with('folderAccessUsers:id')
            ->get();
        $accessibleStartups = $startups
            ->filter(fn (Startup $startup): bool => $startup->userCanAccessFolder($request->user()))
            ->keyBy(static fn (Startup $startup): string => Str::slug($startup->name));
        $activeAccessibleStartups = $accessibleStartups->reject(
            static fn (Startup $startup): bool => $startup->trashed()
        );

        $submissions = FormSubmission::query()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->filter(function (FormSubmission $submission) use ($accessibleStartups): bool {
                return $accessibleStartups->has(Str::slug($submission->startup_name))
                    || ! Startup::withTrashed()->where('name', $submission->startup_name)->exists();
            })
            ->map(static fn (FormSubmission $submission): array => [
                'id' => 'submission-'.$submission->getKey(),
                'submissionId' => $submission->getKey(),
                'form' => $submission->form_id,
                'title' => $submission->form_name,
                'startup' => $submission->startup_name,
                'startupSlug' => Str::slug($submission->startup_name),
                'track' => $submission->track,
                'status' => match ($submission->approval_status) {
                    ApprovalStatus::Approved => 'Approved',
                    ApprovalStatus::InReview => 'In review',
                    ApprovalStatus::Rejected => 'Rejected',
                    default => 'Submitted',
                },
                'date' => $submission->created_at?->format('M j'),
                'userName' => $submission->user?->name ?? 'Submitted form',
                'data' => $submission->data,
                'attachmentName' => $submission->prerequisite_original_name,
                'attachmentUrl' => $submission->prerequisite_path
                    ? route('form-submissions.attachment', $submission)
                    : null,
                'driveResponseUrl' => $submission->google_drive_response_url,
                'driveAttachmentUrl' => $submission->google_drive_attachment_url,
            ])
            ->all();

        $foldersBySlug = $activeAccessibleStartups
            ->mapWithKeys(static fn (Startup $startup): array => [
                Str::slug($startup->name) => [
                    'id' => $startup->id,
                    'slug' => Str::slug($startup->name),
                    'name' => $startup->name,
                    'track' => $startup->track,
                    'restricted' => (bool) $startup->folder_access_restricted,
                    'canManageAccess' => $startup->folder_access_manager_id === null,
                    'accessUserIds' => $startup->folderAccessUsers->pluck('id')->all(),
                ],
            ]);

        $foldersBySlug = $foldersBySlug->map(function (array $folder) use ($activeAccessibleStartups, $request): array {
            $startup = $activeAccessibleStartups->firstWhere('id', $folder['id']);
            $folder['canManageAccess'] = $startup?->userCanManageFolderAccess($request->user()) ?? false;

            return $folder;
        });

        foreach ($submissions as $submission) {
            $foldersBySlug[$submission['startupSlug']] ??= [
                'id' => null,
                'slug' => $submission['startupSlug'],
                'name' => $submission['startup'],
                'track' => $submission['track'],
                'restricted' => false,
                'canManageAccess' => false,
                'accessUserIds' => [],
            ];
        }

        $driveLinks = StartupDriveLink::query()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->filter(static fn (StartupDriveLink $link): bool => $accessibleStartups->has(Str::slug($link->startup_name)))
            ->map(static fn (StartupDriveLink $link): array => [
                'id' => $link->id,
                'startupId' => (int) $link->startup_id,
                'startupSlug' => Str::slug($link->startup_name),
                'label' => $link->label,
                'url' => $link->url,
                'userName' => $link->user?->name ?? 'Marian TBI team',
                'date' => $link->updated_at?->format('M j'),
            ])
            ->all();
        $googleDriveFolders = $activeAccessibleStartups
            ->filter(static fn (Startup $startup): bool => filled($startup->google_drive_folder_url))
            ->map(static fn (Startup $startup): array => [
                'id' => 'google-folder-'.$startup->id,
                'startupId' => (int) $startup->id,
                'startupSlug' => Str::slug($startup->name),
                'label' => 'Marian TBI Google Drive folder',
                'url' => $startup->google_drive_folder_url,
                'userName' => 'Marian TBI Connect',
                'date' => $startup->updated_at?->format('M j'),
            ])
            ->all();
        $driveLinks = [...$driveLinks, ...$googleDriveFolders];
        $driveConnection = GoogleDriveConnection::query()->where('singleton_key', 'marian-tbi')->first();

        return view('files', [
            'startupFolders' => $foldersBySlug->values()->all(),
            'savedSubmissions' => $submissions,
            'driveLinks' => $driveLinks,
            'teamUsers' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'driveConnected' => $driveConnection !== null,
            'driveAccountEmail' => $driveConnection?->google_email,
            'driveConfigured' => config('services.google.client_id')
                && config('services.google.client_secret')
                && config('services.google.drive_account_email'),
            'openStartupSlug' => $request->query('startup'),
            'openTrack' => in_array($request->query('track'), ['internal', 'external'], true)
                ? $request->query('track')
                : 'internal',
        ]);
    }
}
