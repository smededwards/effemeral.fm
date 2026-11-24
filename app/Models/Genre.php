<?php

namespace App\Models;

use App\Models\Concerns\HasEfmId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Normalised style label used to group catalogue artists.
 */
class Genre extends Model
{
    use HasEfmId;

    /** EFMID entity type used in generated identifiers. */
    protected const EFM_TYPE = 'GEN';

    protected $fillable = ['name', 'normalised_name'];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): string => mb_strtolower($value),
        );
    }

    /**
     * Limit the catalogue to approved, non-numeric style labels.
     *
     * @param  Builder<Genre>  $query  The genre query.
     * @return Builder<Genre> The constrained query.
     */
    public function scopeFilterable(Builder $query): Builder
    {
        return $query->whereKey(self::filterableIds());
    }

    /**
     * @return list<int> The associated artist IDs.
     */
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

    /**
     * Return artists carrying this accepted or suppressed style label.
     */
    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->withPivot('source', 'suppressed')->withTimestamps();
    }
}
