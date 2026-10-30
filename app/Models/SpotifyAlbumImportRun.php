<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotifyAlbumImportRun extends Model
{
    protected $fillable = [
        'source', 'status', 'requested_limit', 'fetched_count', 'imported_count',
        'updated_count', 'skipped_count', 'error', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
