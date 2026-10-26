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
        $submission = $action->execute(
            user: $user,
            formId: (int) $validated['form_id'],
            startup: $validated['startup'],
            track: $validated['track'],
            data: $validated['data'],
            signerName: $validated['signer_name'],
            prerequisite: $request->file('prerequisite'),
        );

        if ($user->email) {
            Mail::to($user->email)->queue(new FormSubmittedReceipt($submission));
        }

        $redirect = to_route('files', [
            'startup' => Str::slug($submission->startup_name),
            'track' => $submission->track,
        ]);

        $redirect->with('status', 'Form submitted and saved to '.$submission->startup_name.' file folder.');

        if ($drive->isConnected()) {
            SyncFormSubmissionToDriveJob::dispatch($submission);

            $redirect->with('drive_sync_status', 'The form response was queued for Google Drive synchronization.');
        }

        return $redirect;
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
                ] : []),
            ])->save();
        } catch (\Throwable $exception) {
            if ($uploaded !== null) {
                Storage::disk('local')->delete($uploaded->path);
            }

            throw $exception;
        }

        if ($uploaded !== null && $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $redirect = to_route('files', [
            'startup' => Str::slug($submission->startup_name),
            'track' => $submission->track,
        ]);

        $redirect->with('status', 'Form response updated for '.$submission->startup_name.'.');

        if ($drive->isConnected()) {
            SyncFormSubmissionToDriveJob::dispatch($submission);

            $redirect->with('drive_sync_status', 'The updated form response was queued for Google Drive synchronization.');
        }

        return $redirect;
    }

    public function destroy(Request $request, FormSubmission $submission, GoogleDriveService $drive): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $startup = Startup::withTrashed()->where('name', $submission->startup_name)->first();
        abort_if($startup && ! $startup->userCanAccessFolder($user), 403);

        if ($submission->prerequisite_path) {
            Storage::disk('local')->delete($submission->prerequisite_path);
        }

        $driveWarning = null;
        $driveFileIds = array_values(array_filter([
            $submission->google_drive_response_file_id,
            $submission->google_drive_attachment_file_id,
        ]));
        if ($drive->isConnected() && $driveFileIds !== []) {
            try {
                foreach ($driveFileIds as $fileId) {
                    $drive->deleteFile($fileId);
                }
            } catch (ConnectionException|RequestException|\RuntimeException $exception) {
                Log::error('Form submission was deleted locally but Google Drive files could not be removed.', [
                    'submission_id' => $submission->getKey(),
                    'exception' => $exception,
                ]);
                $driveWarning = 'The response was deleted in Marian TBI Connect but some Google Drive copies could not be removed.';
            }
        }

        $startupName = $submission->startup_name;
        $track = $submission->track;
        $submission->delete();

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
        if ($submission->google_drive_folder_id === $targetFolderId) {
            return $sourceRedirect->with('drive_sync_warning', 'The file is already in that Drive folder.');
        }

        $previousFolderId = $submission->google_drive_folder_id ?? $sourceStartup?->google_drive_folder_id;
        $submission->forceFill([
            'google_drive_folder_id' => $targetFolderId,
        ])->save();

        $driveWarning = null;
        $driveFileIds = array_values(array_filter([
            $submission->google_drive_response_file_id,
            $submission->google_drive_attachment_file_id,
        ]));
        if ($drive->isConnected() && $driveFileIds !== []) {
            SyncFormSubmissionToDriveJob::dispatch($submission, $targetFolderId, $previousFolderId);
        } elseif (! $drive->isConnected() && $driveFileIds !== []) {
            $driveWarning = 'Google Drive is not connected, so the Google Drive copies were not relocated. Connect Drive and retry.';
        }

        $redirect = $sourceRedirect->with('status', 'File moved to custom Drive folder successfully.');
        if ($driveWarning !== null) {
            $redirect->with('drive_sync_warning', $driveWarning);
        }

        return $redirect;
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

        $startup = Startup::withTrashed()->where('name', $submission->startup_name)->first();
        abort_if($startup && ! $startup->userCanAccessFolder(request()->user()), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($submission->prerequisite_path), 404);

        return $disk->download(
            $submission->prerequisite_path,
            $submission->prerequisite_original_name ?? basename($submission->prerequisite_path)
        );
    }
}
