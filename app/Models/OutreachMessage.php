<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutreachMessage extends Model
{
    protected $fillable = [
        'event_id',
        'sender_id',
        'message_type',
        'subject',
        'body',
        'attachments',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
