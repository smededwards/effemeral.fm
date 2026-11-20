<?php

namespace App\Models;

use Database\Factories\RecommendationRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecommendationRun extends Model
{
    protected $fillable = [
        'source', 'status', 'generated_at', 'spotify_playlist_id', 'spotify_playlist_url',
        'synced_at', 'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'immutable_datetime',
            'synced_at' => 'immutable_datetime',
        ];
    }

    /** @use HasFactory<RecommendationRunFactory> */
    use HasFactory;

    public function albumRecommendations(): HasMany
    {
        return $this->hasMany(AlbumRecommendation::class)->orderBy('position');
    }
}
