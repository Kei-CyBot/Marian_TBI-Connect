<?php

namespace App\Models;

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
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'virtual' => 'boolean',
        ];
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(EventInvitation::class);
    }
}
