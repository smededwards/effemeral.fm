<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    protected $fillable = ['name', 'normalised_name'];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): string => mb_strtolower($value),
        );
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->withPivot('source', 'suppressed')->withTimestamps();
    }
}
