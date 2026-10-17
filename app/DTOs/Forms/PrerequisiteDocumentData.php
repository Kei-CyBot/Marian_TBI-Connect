<?php

namespace App\DTOs\Forms;

use InvalidArgumentException;

final readonly class PrerequisiteDocumentData
{
    /**
     * @param  list<string>  $allowedExtensions
     */
    public function __construct(
        public string $key,
        public string $label,
        public bool $required,
        public array $allowedExtensions,
        public int $maxKilobytes,
        public string $filenamePattern,
    ) {
        if ($allowedExtensions === [] || $maxKilobytes < 1) {
            throw new InvalidArgumentException('Prerequisite documents require allowed extensions and a positive size limit.');
        }
    }

    /**
     * @return array{key:string,label:string,required:bool,allowedExtensions:list<string>,maxKilobytes:int}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'required' => $this->required,
            'allowedExtensions' => $this->allowedExtensions,
            'maxKilobytes' => $this->maxKilobytes,
        ];
    }
}
