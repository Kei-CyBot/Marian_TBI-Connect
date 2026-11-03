<?php

namespace App\Jobs;

use App\Models\FormSubmission;
use App\Support\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncFormSubmissionToDriveJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

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
        try {
            $submission = FormSubmission::query()->find($this->submissionId);
            if (! $submission) {
                return;
            }

            if (! $driveService->isConnected()) {
                throw new \RuntimeException('Google Drive is not configured or connected.');
            }

            if ($this->moveToFolderId === null) {
                if (! $driveService->syncSubmission($submission)) {
                    Log::warning('Google Drive sync skipped: '.GoogleDriveService::NOT_CONFIGURED_MESSAGE);
                }

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
        } catch (\Illuminate\Http\Client\HttpClientException|\RuntimeException $e) {
            Log::warning('Google Drive Sync failed: '.$e->getMessage());

            throw $e;
        }
    }
}
