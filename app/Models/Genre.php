<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    protected $fillable = ['name', 'normalised_name'];

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->withPivot('source', 'suppressed')->withTimestamps();
    }
}
