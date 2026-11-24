<?php

namespace App\Services\Recommendations;

use App\Models\AlbumPlaylist;
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
        $titles = [];

        foreach ($reviewAlbums as $item) {
            $titles[] = $item['recommendation']->spotifySavedAlbum->name ?? $item['recommendation']->album->title ?? '';
        }

        return $this->suggestFromTitles($titles);
    }

    /**
     * Suggest a unique two-word playlist name from album titles or a curated fallback list.
     *
     * @param  iterable<string>  $titles  The album titles available to name the playlist.
     * @return string The suggested playlist name.
     */
    public function suggestFromTitles(iterable $titles): string
    {
        $words = $this->extractWords($titles);
        $counts = array_count_values($words);
        arsort($counts);
        $selected = array_slice(array_keys($counts), 0, 2);

        while (count($selected) < 2) {
            $selected = $this->fallbackWords($selected);
        }

        $suggestion = implode(' ', array_map(fn (string $word): string => mb_convert_case($word, MB_CASE_TITLE), $selected));
        $existingNames = SpotifyPlaylist::query()->pluck('name')
            ->merge(AlbumPlaylist::query()->pluck('name'))
            ->map(fn (string $name): string => mb_strtolower($name))
            ->all();
        $uniqueSuggestion = $suggestion;
        $suffix = 2;

        while (in_array(mb_strtolower($uniqueSuggestion), $existingNames, true)) {
            $uniqueSuggestion = $suggestion.' '.$suffix++;
        }

        return $uniqueSuggestion;
    }

    /**
     * @param  iterable<string>  $titles  The album titles to tokenise.
     * @return array<int, string> The usable title words.
     */
    private function extractWords(iterable $titles): array
    {
        $stopWords = ['a', 'an', 'and', 'for', 'in', 'of', 'the', 'to'];
        $words = [];

        foreach ($titles as $title) {
            $titleWords = preg_split('/[^\pL\pN]+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($titleWords as $word) {
                $word = mb_strtolower($word);
                if (mb_strlen($word) > 2 && ! in_array($word, $stopWords, true)) {
                    $words[] = $word;
                }
            }
        }

        return $words;
    }

    /**
     * @param  array<int, string>  $selected  Words already selected for the suggestion.
     * @return array<int, string> Two selected words.
     */
    private function fallbackWords(array $selected): array
    {
        $words = [
            'ash', 'cinder', 'earth', 'echo', 'ember', 'estuary', 'fossil', 'garden',
            'hollow', 'lantern', 'mourning', 'night', 'silver', 'static', 'thorn', 'wilderness',
        ];

        while (count($selected) < 2) {
            $candidate = $words[random_int(0, count($words) - 1)];
            if (! in_array($candidate, $selected, true)) {
                $selected[] = $candidate;
            }
        }

        return $selected;
    }
}
