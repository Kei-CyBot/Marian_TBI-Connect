<?php

namespace App\Models;

use App\Enums\Forms\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'form_id',
        'form_name',
        'startup_name',
        'track',
        'data',
        'approval_status',
        'prerequisite_path',
        'prerequisite_original_name',
        'prerequisite_mime_type',
        'prerequisite_size_bytes',
        'google_drive_response_file_id',
        'google_drive_response_url',
        'google_drive_attachment_file_id',
        'google_drive_attachment_url',
        'drive_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'approval_status' => ApprovalStatus::class,
            'drive_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
