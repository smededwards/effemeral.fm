<?php

namespace App\Models;

use Database\Factories\AlbumRecommendationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered album inside a recommendation draft.
 */
class AlbumRecommendation extends Model
{
    protected $fillable = [
        'recommendation_run_id', 'album_id', 'spotify_saved_album_id', 'position', 'heard_tracks',
        'total_tracks', 'completion_percentage', 'match_reason', 'included',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'heard_tracks' => 'integer',
            'total_tracks' => 'integer',
            'completion_percentage' => 'integer',
            'included' => 'boolean',
        ];
    }

    /**
     * @use HasFactory<AlbumRecommendationFactory>
     */
    use HasFactory;

    /**
     * Return the recommendation draft containing this album.
     */
    public function recommendationRun(): BelongsTo
    {
        return $this->belongsTo(RecommendationRun::class);
    }

    /**
     * Return the optional Spotify saved-album source record.
     */
    public function spotifySavedAlbum(): BelongsTo
    {
        return $this->belongsTo(SpotifySavedAlbum::class);
    }

    /**
     * Return the canonical album being recommended.
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }
}
