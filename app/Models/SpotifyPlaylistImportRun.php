<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persisted resumable Spotify playlist import progress.
 */
class SpotifyPlaylistImportRun extends Model
{
    protected $fillable = [
        'source', 'status', 'requested_limit', 'next_offset', 'total_count', 'fetched_count', 'imported_count',
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
