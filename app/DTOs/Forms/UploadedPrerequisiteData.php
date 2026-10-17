<?php

namespace App\DTOs\Forms;

final readonly class UploadedPrerequisiteData
{
    public function __construct(
        public string $path,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
    ) {}

    /**
     * @return array{path:string,original_name:string,mime_type:string,size_bytes:int}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
        ];
    }
}
