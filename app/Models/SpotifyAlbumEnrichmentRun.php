<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persisted progress and retry state for Spotify album enrichment.
 *
 * @property array<int, int>|null $failed_album_ids Album IDs that failed during enrichment.
 */
class SpotifyAlbumEnrichmentRun extends Model
{
    protected $fillable = [
        'scope', 'status', 'priority_phase', 'requested_limit', 'pause_every', 'pause_seconds', 'last_album_id',
        'total_candidates', 'processed_count', 'enriched_count', 'failed_count', 'failed_album_ids',
        'error', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'failed_album_ids' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
