<?php

namespace App\Http\Controllers;

use App\Actions\Forms\SubmitFormAction;
use App\Http\Requests\Forms\SubmitFormRequest;
use App\Models\FormSubmission;
use App\Models\Startup;
use App\Models\User;
use App\Support\GoogleDriveService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FormSubmissionController extends Controller
{
    public function store(
        SubmitFormRequest $request,
        SubmitFormAction $action,
        GoogleDriveService $drive
    ): RedirectResponse
    {
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

        $redirect = to_route('files', [
            'startup' => Str::slug($submission->startup_name),
            'track' => $submission->track,
        ]);

        if (! $drive->isConnected()) {
            return $redirect->with('status', 'Form submitted and saved to '.$submission->startup_name.' file folder.');
        }

        try {
            $drive->syncSubmission($submission);
        } catch (ConnectionException|RequestException|\RuntimeException $exception) {
            Log::error('Submitted form was saved locally but could not sync to Google Drive.', [
                'submission_id' => $submission->getKey(),
                'exception' => $exception,
            ]);

            return $redirect
                ->with('status', 'Form submitted and saved locally to '.$submission->startup_name.' file folder.')
                ->with('drive_sync_warning', 'The form was saved in Marian TBI Connect but Google Drive sync failed. Retry using Sync to Google Drive.');
        }

        return $redirect
            ->with('status', 'Form submitted and saved to '.$submission->startup_name.' file folder.')
            ->with('drive_sync_status', 'The form response and attachment were synchronized to Google Drive.');
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
