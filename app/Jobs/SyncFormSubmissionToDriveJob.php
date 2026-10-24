<?php

namespace App\Jobs;

use App\Models\FormSubmission;
use App\Support\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncFormSubmissionToDriveJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public int $submissionId;

    public function __construct(
        FormSubmission|int $submission,
        public ?string $moveToFolderId = null,
        public ?string $moveFromFolderId = null,
    ) {
        $this->submissionId = $submission instanceof FormSubmission
            ? (int) $submission->getKey()
            : $submission;
    }

    public function handle(GoogleDriveService $driveService): void
    {
        $submission = FormSubmission::query()->find($this->submissionId);
        if (! $submission || ! $driveService->isConnected()) {
            return;
        }

        if ($this->moveToFolderId === null) {
            $driveService->syncSubmission($submission);

            return;
        }

        $fileIds = array_values(array_filter([
            $submission->google_drive_response_file_id,
            $submission->google_drive_attachment_file_id,
        ]));
        foreach ($fileIds as $fileId) {
            $driveService->moveFile($fileId, $this->moveToFolderId, $this->moveFromFolderId);
        }

        $submission->forceFill(['drive_synced_at' => now()])->save();
    }
}
