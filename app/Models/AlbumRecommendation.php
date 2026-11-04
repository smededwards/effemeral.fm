<?php

namespace App\Models;

use Database\Factories\AlbumRecommendationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlbumRecommendation extends Model
{
    protected $fillable = [
        'recommendation_run_id', 'spotify_saved_album_id', 'position', 'heard_tracks',
        'total_tracks', 'completion_percentage', 'included',
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

    /** @use HasFactory<AlbumRecommendationFactory> */
    use HasFactory;

    public function recommendationRun(): BelongsTo
    {
        return $this->belongsTo(RecommendationRun::class);
    }

    public function spotifySavedAlbum(): BelongsTo
    {
        return $this->belongsTo(SpotifySavedAlbum::class);
    }
}
