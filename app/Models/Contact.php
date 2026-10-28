<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'side',
        'name',
        'email',
        'phone',
        'affiliation_type',
        'role',
        'organization',
        'academic_program',
        'academic_year',
        'domains',
        'domain_other',
        'technical_skills',
        'technical_other',
        'skill_details',
        'nda_status',
        'verification_status',
        'incubatees',
        'activity_log',
        'archived_by',
        'archive_reason',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'technical_skills' => 'array',
            'incubatees' => 'array',
            'activity_log' => 'array',
        ];
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function startupAssignments(): HasMany
    {
        return $this->hasMany(ContactStartupAssignment::class);
    }
}
