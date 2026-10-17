<?php

namespace App\Enums\Forms;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Email = 'email';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case File = 'file';
    case Signature = 'signature';
}
