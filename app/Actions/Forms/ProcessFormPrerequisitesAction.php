<?php

namespace App\Actions\Forms;

use App\DTOs\Forms\PrerequisiteDocumentData;
use App\DTOs\Forms\UploadedPrerequisiteData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ProcessFormPrerequisitesAction
{
    public function execute(
        UploadedFile $file,
        PrerequisiteDocumentData $requirement,
        int $formId,
        string $startupName,
    ): UploadedPrerequisiteData {
        if (
            ! $file->isValid()
            || ! in_array($file->extension(), $requirement->allowedExtensions, true)
            || (int) $file->getSize() > $requirement->maxKilobytes * 1024
        ) {
            throw new InvalidArgumentException('The uploaded prerequisite does not match its document requirements.');
        }

        $filename = strtr($requirement->filenamePattern, [
            '{startup-slug}' => Str::slug($startupName),
            '{uuid}' => (string) Str::uuid(),
        ]);
        $path = $file->storeAs('form-prerequisites', $filename, 'local');

        if ($path === false) {
            throw new \RuntimeException('Unable to store the uploaded prerequisite document.');
        }

        return new UploadedPrerequisiteData(
            path: $path,
            originalName: mb_substr(basename($file->getClientOriginalName()), 0, 255),
            mimeType: $file->getMimeType() ?? 'application/octet-stream',
            sizeBytes: (int) $file->getSize(),
        );
    }
}
