<?php

namespace App\Services\Search;

/**
 * Normalises user-entered search terms without changing stored catalogue data.
 */
class SearchTermNormalizer
{
    /**
     * Remove harmless boundary punctuation and standardise case and spacing.
     *
     * @param  string  $value  The user-entered search term.
     * @return string The normalised search term.
     */
    public function normalize(string $value): string
    {
        $value = preg_replace('/^[\s\p{P}]+|[\s\p{P}]+$/u', '', trim($value)) ?? $value;

        return preg_replace('/\s+/', ' ', mb_strtolower(trim($value))) ?? '';
    }
}
