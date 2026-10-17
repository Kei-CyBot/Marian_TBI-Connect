<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StartupAssessment extends Model
{
    protected $fillable = [
        'startup_id',
        'startup_name',
        'competency_ratings',
        'other_critical_skills',
        'total_points',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'competency_ratings' => 'array',
            'other_critical_skills' => 'array',
            'total_points' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
