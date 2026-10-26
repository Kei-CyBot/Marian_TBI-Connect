<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntakeSubmission extends Model
{
    protected $fillable = [
        'type',
        'name',
        'email',
        'track',
        'data',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
