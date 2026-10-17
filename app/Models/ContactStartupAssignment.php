<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactStartupAssignment extends Model
{
    protected $fillable = [
        'contact_id',
        'startup_id',
        'assigned_by',
        'confirmed_by',
        'verification_method',
        'verification_notes',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function startup(): BelongsTo
    {
        return $this->belongsTo(Startup::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
