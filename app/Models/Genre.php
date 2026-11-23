<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function scopeFilterable(Builder $query): Builder
    {
        return $query->whereKey(self::filterableIds());
    }

    /** @return list<int> */
    public static function filterableIds(): array
    {
        return static::query()->get(['id', 'normalised_name'])
            ->filter(fn (self $genre): bool => self::isFilterableName($genre->normalised_name))
            ->modelKeys();
    }

    public static function isFilterableName(string $normalisedName): bool
    {
        $words = preg_split('/\s+/', trim($normalisedName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $allowedWords = array_fill_keys((array) config('genres.filter_words', []), true);

        return $words !== [] && collect($words)->every(fn (string $word): bool => isset($allowedWords[$word]));
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->withPivot('source', 'suppressed')->withTimestamps();
    }
}
