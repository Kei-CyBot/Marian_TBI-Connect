<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'title',
        'scope',
        'kind',
        'starts_at',
        'ends_at',
        'location',
        'virtual',
        'host',
        'status',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'actual_attendees_count',
        'post_event_notes',
        'post_event_document_path',
        'post_event_document_original_name',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'virtual' => 'boolean',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
            'actual_attendees_count' => 'integer',
        ];
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(EventInvitation::class);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['scheduled', 'in_progress'])
            ->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereNotNull('archived_at')
            ->orWhereIn('status', ['completed', 'cancelled']));
    }
}
