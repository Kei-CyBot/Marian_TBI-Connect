<?php

namespace App\DTOs\Forms;

use App\Enums\Forms\FieldType;
use App\Enums\Forms\ValidationRule;
use InvalidArgumentException;

final readonly class FormFieldData
{
    /**
     * @param  list<string>  $options
     * @param  list<ValidationRule>  $rules
     * @param  array<string, int|float|string>  $ruleParameters
     */
    public function __construct(
        public string $key,
        public string $label,
        public FieldType $type,
        public bool $required = false,
        public array $options = [],
        public array $rules = [],
        public array $ruleParameters = [],
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException('Form field keys must use lowercase snake case.');
        }

        if (in_array($type, [FieldType::Select, FieldType::Radio], true) && $options === []) {
            throw new InvalidArgumentException('Select and radio fields require at least one option.');
        }
    }

    /**
     * @return array{key:string,label:string,type:string,required:bool,options:list<string>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
            'options' => $this->options,
        ];
    }
}
