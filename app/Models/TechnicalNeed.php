<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalNeed extends Model
{
    protected $fillable = [
        'startup_id',
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
        'approval_document_path',
        'approval_document_original_name',
    ];

    protected function casts(): array
    {
        return [
            'subscription_price' => 'decimal:2',
            'date_entry' => 'date:Y-m-d',
            'date_needed' => 'date:Y-m-d',
        ];
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class);
    }
}
