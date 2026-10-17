<?php

namespace App\Actions\Forms;

use App\DTOs\Forms\UploadedPrerequisiteData;
use App\Enums\Forms\ApprovalStatus;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Forms\StartupFormCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class SubmitFormAction
{
    public function __construct(
        private readonly StartupFormCatalog $catalog,
        private readonly ProcessFormPrerequisitesAction $prerequisites,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        User $user,
        int $formId,
        string $startup,
        string $track,
        array $data,
        string $signerName,
        ?UploadedFile $prerequisite,
    ): FormSubmission {
        $form = $this->catalog->find($formId);
        if ($form === null) {
            throw new RuntimeException('The selected form is not in the startup form catalog.');
        }

        $uploaded = null;
        if ($form->prerequisite !== null) {
            if ($prerequisite === null) {
                throw new RuntimeException('The selected form requires a prerequisite document.');
            }

            $uploaded = $this->prerequisites->execute($prerequisite, $form->prerequisite, $formId, $startup);
        }

        try {
            return DB::transaction(fn (): FormSubmission => FormSubmission::query()->create([
                'user_id' => $user->getKey(),
                'form_id' => $form->id,
                'form_name' => $form->name,
                'startup_name' => $startup,
                'track' => $track,
                'data' => [...$data, 'signer_name' => $signerName],
                'approval_status' => ApprovalStatus::Submitted,
                'prerequisite_path' => $uploaded?->path,
                'prerequisite_original_name' => $uploaded?->originalName,
                'prerequisite_mime_type' => $uploaded?->mimeType,
                'prerequisite_size_bytes' => $uploaded?->sizeBytes,
            ]));
        } catch (\Throwable $exception) {
            if ($uploaded instanceof UploadedPrerequisiteData) {
                Storage::disk('local')->delete($uploaded->path);
            }

            throw $exception;
        }
    }
}
