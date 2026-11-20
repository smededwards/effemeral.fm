<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotifyConnection extends Model
{
    protected $fillable = [
        'access_token', 'refresh_token', 'spotify_user_id', 'display_name', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
