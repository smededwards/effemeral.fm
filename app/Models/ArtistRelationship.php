<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provider-supplied relationship between a canonical artist and another artist.
 */
class ArtistRelationship extends Model
{
    protected $fillable = [
        'artist_id',
        'related_artist_id',
        'related_provider_id',
        'related_name',
        'relationship_type',
        'source',
        'metadata',
    ];

    /**
     * Cast provider relationship metadata for application use.
     */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /**
     * Return the canonical artist from which this relationship originates.
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Return the related canonical artist when it is already in the catalogue.
     */
    public function relatedArtist(): BelongsTo
    {
        return $this->belongsTo(Artist::class, 'related_artist_id');
    }
}
