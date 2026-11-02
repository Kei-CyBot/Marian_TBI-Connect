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

    public function trackingId(): string
    {
        return 'MTBI-'.strtoupper(substr($this->type, 0, 1)).'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
