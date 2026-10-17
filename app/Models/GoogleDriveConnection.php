<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleDriveConnection extends Model
{
    protected $fillable = [
        'singleton_key',
        'google_email',
        'access_token',
        'refresh_token',
        'expires_at',
        'root_folder_id',
        'root_folder_url',
        'connected_by',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }
}
