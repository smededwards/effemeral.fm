<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provider-supplied name for a canonical artist.
 */
class ArtistAlias extends Model
{
    protected $fillable = [
        'artist_id',
        'name',
        'normalised_name',
        'locale',
        'alias_type',
        'source',
    ];

    /**
     * Return the canonical artist owning this alias.
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
