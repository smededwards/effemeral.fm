<?php

namespace App\Services\Recommendations;

use App\Models\SpotifyPlaylist;

/**
 * Generates a readable, unused name for an album recommendation draft.
 */
class PlaylistNameSuggester
{
    /**
     * @param  iterable<array<string, mixed>>  $reviewAlbums  The albums in the draft.
     */
    public function suggest(iterable $reviewAlbums): string
    {
        $stopWords = ['a', 'an', 'and', 'for', 'in', 'of', 'the', 'to'];
        $words = [];

        foreach ($reviewAlbums as $item) {
            $title = $item['recommendation']->spotifySavedAlbum->name ?? $item['recommendation']->album->title ?? '';
            $titleWords = preg_split('/[^\pL\pN]+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($titleWords as $word) {
                $word = mb_strtolower($word);
                if (mb_strlen($word) > 2 && ! in_array($word, $stopWords, true)) {
                    $words[] = $word;
                }
            }
        }

        $counts = array_count_values($words);
        arsort($counts);
        $selected = array_slice(array_keys($counts), 0, 2);

        while (count($selected) < 2) {
            $selected[] = count($selected) === 0 ? 'album' : 'draft';
        }

        $suggestion = implode(' ', array_map(fn (string $word): string => mb_convert_case($word, MB_CASE_TITLE), $selected));
        $existingNames = SpotifyPlaylist::query()->pluck('name')->map(fn (string $name): string => mb_strtolower($name))->all();
        $uniqueSuggestion = $suggestion;
        $suffix = 2;

        while (in_array(mb_strtolower($uniqueSuggestion), $existingNames, true)) {
            $uniqueSuggestion = $suggestion.' '.$suffix++;
        }

        return $uniqueSuggestion;
    }
}
