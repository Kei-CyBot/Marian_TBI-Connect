<?php

namespace App\Http\Controllers;

use App\Actions\Forms\ProcessFormPrerequisitesAction;
use App\Actions\Forms\SubmitFormAction;
use App\Enums\Forms\ApprovalStatus;
use App\Http\Requests\Forms\SubmitFormRequest;
use App\Jobs\SyncFormSubmissionToDriveJob;
use App\Mail\FormSubmittedReceipt;
use App\Models\ActivityLog;
use App\Models\FormSubmission;
use App\Models\Startup;
use App\Models\StartupDriveLink;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Forms\StartupFormCatalog;
use App\Support\GoogleDriveService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormSubmissionController extends Controller
{
    public function store(
        SubmitFormRequest $request,
        SubmitFormAction $action,
        GoogleDriveService $drive
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validated();
        $startup = Startup::withTrashed()->where('name', $validated['startup'])->first();
        abort_if($startup && ! $startup->userCanAccessFolder($user), 403);
        try {
            $submission = $action->execute(
                user: $user,
                formId: (int) $validated['form_id'],
                startup: $validated['startup'],
                track: $validated['track'],
                data: $validated['data'],
                signerName: $validated['signer_name'],
                prerequisite: $request->file('prerequisite'),
            );

            // Ensure local storage saved if there's a prerequisite
            if ($submission->prerequisite_path) {
                try {
                    if (! Storage::disk('local')->exists($submission->prerequisite_path)) {
                        Log::error('Form submission prerequisite missing after save', ['submission_id' => $submission->id]);
                    } else {
                        $submission->forceFill(['local_path' => $submission->prerequisite_path])->save();
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to update local_path for submission', ['error' => $e->getMessage(), 'submission_id' => $submission->id]);
                }
            }

            // Try to save to public disk as well if possible
            try {
                if ($submission->prerequisite_path && Storage::disk('local')->exists($submission->prerequisite_path)) {
                    $contents = Storage::disk('local')->get($submission->prerequisite_path);
                    $publicName = 'form-submissions/'.basename($submission->prerequisite_path);
                    Storage::disk('public')->put($publicName, $contents);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to save file to public disk', ['error' => $e->getMessage()]);
            }

            if ($user->email) {
                Mail::to($user->email)->queue(new FormSubmittedReceipt($submission));
            }

            $redirect = to_route('files', [
                'startup' => Str::slug($submission->startup_name),
                'track' => $submission->track,
            ]);

            $redirect->with('status', 'Form submitted and saved to '.$submission->startup_name.' file folder.');

            // Attempt Drive sync if connected or user has refresh token - make it async/safe
            if ($drive->isConnected() || ($user->google_refresh_token)) {
                try {
                    SyncFormSubmissionToDriveJob::dispatch($submission);
                    $redirect->with('drive_sync_status', 'The form response was queued for Google Drive synchronization.');
                } catch (\Throwable $e) {
                    $drive->logSyncFailure($e, ['submission_id' => $submission->id]);
                    $redirect->with('drive_sync_warning', 'File saved locally. Cloud backup will retry shortly.');
                }
            }

            return $redirect;
        } catch (\Throwable $e) {
            Log::error('Form submission failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);

            return back()->withInput()->with('error', 'Form submission failed: '.$e->getMessage());
        }
    }

    public function update(
        SubmitFormRequest $request,
        FormSubmission $submission,
        GoogleDriveService $drive
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $startup = Startup::withTrashed()->where('name', $submission->startup_name)->first();
        abort_if($startup && ! $startup->userCanAccessFolder($user), 403);

        $validated = $request->validated();
        abort_unless(
            (int) $validated['form_id'] === $submission->form_id
                && $validated['startup'] === $submission->startup_name
                && $validated['track'] === $submission->track,
            422,
            'A saved response cannot be moved to a different form or incubatee.'
        );

        $form = app(StartupFormCatalog::class)->find($submission->form_id);
        $preserved = json_decode((string) $request->input('data_complex'), true);
        $data = [
            ...(is_array($preserved) ? $preserved : []),
            ...(is_array($validated['data']) ? $validated['data'] : []),
            'signer_name' => $validated['signer_name'],
        ];

        $file = $request->file('prerequisite');
        $uploaded = null;
        if ($file !== null && $form?->prerequisite !== null) {
            $uploaded = app(ProcessFormPrerequisitesAction::class)->execute(
                $file,
                $form->prerequisite,
                $submission->form_id,
                $submission->startup_name
            );
        }

        $oldPath = $submission->prerequisite_path;

        try {
            $submission->forceFill([
                'data' => $data,
                ...($uploaded !== null ? [
                    'prerequisite_path' => $uploaded->path,
                    'prerequisite_original_name' => $uploaded->originalName,
                    'prerequisite_mime_type' => $uploaded->mimeType,
                    'prerequisite_size_bytes' => $uploaded->sizeBytes,
                    'local_path' => $uploaded->path,
                ] : []),
            ])->save();
        } catch (\Throwable $exception) {
            if ($uploaded !== null) {
                try {
                    Storage::disk('local')->delete($uploaded->path);
                } catch (\Throwable $e) {
                    Log::error('Failed to cleanup uploaded file after update error', ['error' => $e->getMessage()]);
                }
            }
            Log::error('Form submission update failed', ['error' => $exception->getMessage(), 'submission_id' => $submission->id]);
            throw $exception;
        }

        if ($uploaded !== null && $oldPath) {
            try {
                Storage::disk('local')->delete($oldPath);
            } catch (\Throwable $e) {
                Log::error('Failed to delete old file during update', ['error' => $e->getMessage()]);
            }
        }

        // Save to public disk
        try {
            if ($submission->prerequisite_path && Storage::disk('local')->exists($submission->prerequisite_path)) {
                $contents = Storage::disk('local')->get($submission->prerequisite_path);
                $publicName = 'form-submissions/'.basename($submission->prerequisite_path);
                Storage::disk('public')->put($publicName, $contents);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to save updated file to public disk', ['error' => $e->getMessage()]);
        }

        $redirect = to_route('files', [
            'startup' => Str::slug($submission->startup_name),
            'track' => $submission->track,
        ]);

        $redirect->with('status', 'Form response updated for '.$submission->startup_name.'.');

        if ($drive->isConnected() || ($user->google_refresh_token)) {
            try {
                SyncFormSubmissionToDriveJob::dispatch($submission);
                $redirect->with('drive_sync_status', 'The updated form response was queued for Google Drive synchronization.');
            } catch (\Throwable $e) {
                $drive->logSyncFailure($e, ['submission_id' => $submission->id]);
                $redirect->with('drive_sync_warning', 'File saved locally. Cloud backup will retry shortly.');
            }
        }

        return $redirect;
    }

    public function destroy(Request $request, FormSubmission $submission, GoogleDriveService $drive): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $startup = Startup::withTrashed()->where('name', $submission->startup_name)->first();
        abort_if($startup && ! $startup->userCanAccessFolder($user), 403);

        $localPaths = array_values(array_unique(array_filter([
            $submission->prerequisite_path,
            $submission->local_path,
        ])));
        if ($localPaths !== []) {
            Storage::disk('local')->delete($localPaths);
        }
        if ($submission->prerequisite_path) {
            Storage::disk('public')->delete('form-submissions/'.basename($submission->prerequisite_path));
        }

        $driveWarning = null;
        $driveFileIds = array_values(array_unique(array_filter([
            $submission->google_drive_response_file_id,
            $submission->google_drive_attachment_file_id,
            $submission->google_drive_file_id,
        ])));
        if ($drive->isConnected() && $driveFileIds !== []) {
            try {
                foreach ($driveFileIds as $fileId) {
                    $drive->deleteFile($fileId);
                }
            } catch (ConnectionException|RequestException|\RuntimeException $exception) {
                $drive->logSyncFailure($exception, ['submission_id' => $submission->getKey()]);
                $driveWarning = 'The response was deleted in Marian TBI Connect but some Google Drive copies could not be removed.';
            }
        }

        $startupName = $submission->startup_name;
        $track = $submission->track;
        $fileName = $submission->prerequisite_original_name ?: $submission->form_name;
        $submission->delete();

        ActivityLogger::log(
            'file_deleted',
            $driveWarning === null
                ? "File '{$fileName}' removed from local storage and Google Drive."
                : "File '{$fileName}' removed from local storage; Google Drive copies could not all be removed.",
            $submission,
        );

        $redirect = to_route('files', [
            'startup' => Str::slug($startupName),
            'track' => $track,
        ])->with('status', 'Form submission deleted successfully.');
        if ($driveWarning !== null) {
            $redirect->with('drive_sync_warning', $driveWarning);
        }

        return $redirect;
    }

    public function move(Request $request, FormSubmission $submission, GoogleDriveService $drive): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $sourceStartup = Startup::withTrashed()->where('name', $submission->startup_name)->first();
        abort_if($sourceStartup && ! $sourceStartup->userCanAccessFolder($user), 403);

        $validated = $request->validate([
            'target_folder_id' => ['required', 'string', 'max:255'],
        ]);
        $targetFolderId = $validated['target_folder_id'];

        $allowedFolderIds = StartupDriveLink::query()
            ->where('startup_name', $submission->startup_name)
            ->pluck('url')
            ->map(static fn (mixed $url): ?string => GoogleDriveService::parseFolderId((string) $url))
            ->push($sourceStartup?->google_drive_folder_id)
            ->filter()
            ->unique();
        abort_unless(
            $allowedFolderIds->contains($targetFolderId),
            422,
            'The selected folder is not a configured custom Drive folder for this incubatee.'
        );

        $sourceRedirect = to_route('files', [
            'startup' => Str::slug($submission->startup_name),
            'track' => $submission->track,
        ]);

        $previousFolderId = $submission->google_drive_folder_id ?? $sourceStartup?->google_drive_folder_id;
        $driveFileIds = array_values(array_filter([
            $submission->google_drive_response_file_id,
            $submission->google_drive_attachment_file_id,
        ]));

        Log::info('Move initiated', [
            'submission_id' => $submission->getKey(),
            'drive_file_id' => $driveFileIds,
            'target' => $targetFolderId,
        ]);

        if (! $drive->isConnected($user)) {
            $submission->forceFill(['google_drive_folder_id' => $targetFolderId])->save();

            return $sourceRedirect
                ->with('status', 'File moved to custom Drive folder successfully.')
                ->with('drive_sync_warning', 'Google Drive is not connected, so the Google Drive copies were not relocated. Connect Drive and retry.');
        }

        try {
            if ($driveFileIds !== []) {
                foreach ($driveFileIds as $fileId) {
                    $drive->moveFile($fileId, $targetFolderId, $previousFolderId);
                }
            } elseif (! $drive->syncSubmissionFiles($submission, $targetFolderId)) {
                return $sourceRedirect->with('drive_sync_warning', GoogleDriveService::NOT_CONFIGURED_MESSAGE);
            }
        } catch (\Throwable $e) {
            $drive->logSyncFailure($e, [
                'submission_id' => $submission->getKey(),
                'target' => $targetFolderId,
            ]);

            return $sourceRedirect->with('error', 'Google Drive Move Failed: '.$e->getMessage());
        }

        $submission->forceFill([
            'google_drive_folder_id' => $targetFolderId,
            'drive_synced_at' => now(),
        ])->save();

        return $sourceRedirect->with('status', 'File moved to custom Drive folder successfully.');
    }

    public function updateStatus(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $startup = Startup::withTrashed()->where('name', $submission->startup_name)->first();
        abort_if($startup && ! $startup->userCanAccessFolder($user), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                ApprovalStatus::InReview->value,
                ApprovalStatus::Approved->value,
                ApprovalStatus::Rejected->value,
            ])],
        ]);

        $oldStatus = $submission->approval_status;
        $newStatus = ApprovalStatus::from($validated['status']);
        $submission->forceFill(['approval_status' => $newStatus])->saveQuietly();

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'action' => 'status_updated',
            'subject_type' => FormSubmission::class,
            'subject_id' => $submission->getKey(),
            'description' => $user->name.' changed '.$submission->form_name.' for '.$submission->startup_name.' from '.$oldStatus->value.' to '.$newStatus->value,
            'changes' => [
                'approval_status' => [
                    'old' => $oldStatus->value,
                    'new' => $newStatus->value,
                ],
            ],
            'ip_address' => $request->ip(),
        ]);

        return to_route('files', [
            'startup' => Str::slug($submission->startup_name),
            'track' => $submission->track,
        ])->with('status', 'Submission status updated to '.str_replace('_', ' ', $newStatus->value).'.');
    }

    public function downloadAttachment(FormSubmission $submission)
    {
        abort_unless($submission->prerequisite_path, 404);

        $user = request()->user();
        abort_unless($user instanceof User, 401);

        if ($user->isMember()) {
            $memberStartupName = $user->getAttribute('startup_name')
                ?: Startup::query()
                    ->whereRaw('LOWER(contact_email) = ?', [strtolower($user->email)])
                    ->value('name');
            $hasAssignedStartupAccess = Startup::query()
                ->where('name', $submission->startup_name)
                ->where(function ($query) use ($user): void {
                    $query->where('folder_access_manager_id', $user->id)
                        ->orWhereHas('folderAccessUsers', fn ($users) => $users->whereKey($user->id));
                })
                ->exists();

            abort_unless(
                (int) $submission->user_id === (int) $user->id
                    || (filled($memberStartupName) && strcasecmp($submission->startup_name, $memberStartupName) === 0)
                    || $hasAssignedStartupAccess,
                403,
            );
        } else {
            $startup = Startup::withTrashed()->where('name', $submission->startup_name)->first();
            abort_if($startup && ! $startup->userCanAccessFolder($user), 403);
        }

        $disk = Storage::disk('local');
        abort_unless($disk->exists($submission->prerequisite_path), 404);

        return $disk->download(
            $submission->prerequisite_path,
            $submission->prerequisite_original_name ?? basename($submission->prerequisite_path)
        );
    }
}
