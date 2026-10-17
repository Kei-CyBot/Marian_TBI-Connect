<?php

namespace App\DTOs\Forms;

use App\Enums\Forms\FormCategory;

final readonly class FormDefinitionData
{
    /**
     * @param  list<FormFieldData>  $fields
     */
    public function __construct(
        public int $id,
        public string $name,
        public FormCategory $category,
        public bool $attachmentOnly = false,
        public array $fields = [],
        public ?PrerequisiteDocumentData $prerequisite = null,
    ) {}

    /**
     * @return array{id:int,title:string,category:string,tracks:string,attachmentOnly:bool,fields:list<array>,prerequisite:?array}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->name,
            'category' => $this->category->value,
            'tracks' => 'both',
            'attachmentOnly' => $this->attachmentOnly,
            'fields' => array_map(
                static fn (FormFieldData $field): array => $field->toArray(),
                $this->fields
            ),
            'prerequisite' => $this->prerequisite?->toArray(),
        ];
    }
}
