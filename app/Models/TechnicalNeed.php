<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalNeed extends Model
{
    protected $fillable = [
        'startup_id',
        'user_id',
        'requirement',
        'description',
        'subscription_price',
        'months_needed',
        'responsible_team_member',
        'date_entry',
        'date_needed',
        'resource_tool',
        'prospect_resource_person',
        'status',
        'priority',
        'remarks',
        'attachment_path',
        'approval_attachment_path',
        'approval_attachment_original_name',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'subscription_price' => 'decimal:2',
            'date_entry' => 'date:Y-m-d',
            'date_needed' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
        ];
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
