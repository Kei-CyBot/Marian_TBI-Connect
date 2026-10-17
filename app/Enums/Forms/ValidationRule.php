<?php

namespace App\Enums\Forms;

enum ValidationRule: string
{
    case Required = 'required';
    case Nullable = 'nullable';
    case String = 'string';
    case Email = 'email';
    case Integer = 'integer';
    case Numeric = 'numeric';
    case Boolean = 'boolean';
    case Date = 'date';
    case In = 'in';
    case Max = 'max';
    case Min = 'min';
}
