<?php

namespace App\Models;

use App\Models\Concerns\HasEfmId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Canonical track record linked to an album and source identifiers.
 */
class Track extends Model
{
    use HasEfmId;

    /** EFMID entity type used in generated identifiers. */
    protected const EFM_TYPE = 'TRK';

    protected $fillable = [
        'album_id', 'title', 'normalised_title', 'spotify_id', 'lastfm_mbid',
        'spotify_uri', 'disc_number', 'track_number', 'duration_ms',
    ];

    /**
     * Return the album containing the track.
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }
}
