<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Provider-supplied title for a canonical track.
 */
class TrackAlias extends Model
{
    protected $fillable = [
        'track_id', 'name', 'normalised_name', 'source', 'provider_id',
    ];

    /**
     * Return the canonical track owning this alias.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
