<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactRegistrationApplication extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'organization',
        'role',
        'department',
        'affiliation_type',
        'track_stream',
        'stream',
        'application_data',
        'status',
        'reviewed_by',
        'reviewed_at',
        'domain_expertise',
        'technical_skills',
        'tools_methods',
    ];

    protected function casts(): array
    {
        return [
            'application_data' => 'array',
            'reviewed_at' => 'datetime',
            'domain_expertise' => 'array',
            'technical_skills' => 'array',
            'tools_methods' => 'array',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
