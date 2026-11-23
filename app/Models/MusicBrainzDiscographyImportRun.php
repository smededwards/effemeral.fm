<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persisted MusicBrainz enrichment progress for one artist.
 */
class MusicBrainzDiscographyImportRun extends Model
{
    protected $fillable = [
        'artist_id', 'status', 'fetched_count', 'imported_count', 'matched_count',
        'unmatched_count', 'error', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
