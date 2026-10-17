<?php

namespace App\Enums\Forms;

enum FormCategory: string
{
    case Intake = 'intake';
    case Agreement = 'agreement';
    case Training = 'training';
    case Monitoring = 'monitoring';
    case Evaluation = 'evaluation';
    case Governance = 'governance';
    case Planning = 'planning';
    case Transition = 'transition';
}
