<?php

namespace App\Services\Recommendations;

use App\Models\AlbumPlaylist;
use App\Models\PlaylistNameOption;
use App\Models\SpotifyConnection;
use App\Models\SpotifyPlaylist;
use App\Services\Spotify\SpotifyClient;
use Throwable;

/**
 * Generates a readable, unused name for an album recommendation draft.
 */
class PlaylistNameSuggester
{
    public function __construct(private SpotifyClient $spotifyClient) {}

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
        $this->syncSpotifyPlaylistWords();
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
                if (mb_strlen($word) > 2 && preg_match('/\pL/u', $word) === 1 && ! in_array($word, $stopWords, true)) {
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
        $words = PlaylistNameOption::query()->pluck('word')->merge([
            'ash', 'cinder', 'earth', 'echo', 'ember', 'estuary', 'fossil', 'garden',
            'hollow', 'lantern', 'mourning', 'night', 'silver', 'static', 'thorn', 'wilderness',
        ])->unique()->values()->all();

        while (count($selected) < 2) {
            $candidate = $words[random_int(0, count($words) - 1)];
            if (! in_array($candidate, $selected, true)) {
                $selected[] = $candidate;
            }
        }

        return $selected;
    }

    /**
     * Store unique words from playlists owned by the connected Spotify account.
     */
    private function syncSpotifyPlaylistWords(): void
    {
        $connection = SpotifyConnection::query()->latest()->first();
        $ownerId = $connection?->spotify_user_id;

        if (! is_string($ownerId) || $ownerId === '') {
            try {
                $identity = $this->spotifyClient->currentUser();
            } catch (Throwable) {
                return;
            }

            $ownerId = $identity['id'];
            $connection?->update([
                'spotify_user_id' => $ownerId,
                'display_name' => $identity['display_name'],
            ]);
        }

        $words = $this->extractWords(
            SpotifyPlaylist::query()->where('owner_id', $ownerId)->pluck('name'),
        );

        if ($words === []) {
            return;
        }

        $timestamp = now();
        PlaylistNameOption::query()->upsert(
            array_map(
                fn (string $word): array => [
                    'word' => $word,
                    'source' => 'spotify_playlist',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                array_values(array_unique($words)),
            ),
            ['word'],
            ['source', 'updated_at'],
        );
    }
}
