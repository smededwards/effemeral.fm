<?php

namespace App\Services\Spotify;

use App\Models\SpotifyConnection;

/**
 * Defines which Spotify playlists and artists may enter the local catalogue.
 */
class SpotifyImportPolicy
{
    /**
     * Resolve and persist the connected Spotify account ID.
     *
     * @param  SpotifyClient  $client  The authenticated Spotify client.
     * @return string The connected Spotify user ID.
     */
    public function ownerId(SpotifyClient $client): string
    {
        $connection = SpotifyConnection::query()->latest()->first();
        if (is_string($connection?->spotify_user_id) && $connection->spotify_user_id !== '') {
            return $connection->spotify_user_id;
        }

        $identity = $client->currentUser();
        $connection?->update([
            'spotify_user_id' => $identity['id'],
            'display_name' => $identity['display_name'],
        ]);

        return $identity['id'];
    }

    /**
     * Determine whether a playlist belongs to the connected Spotify account.
     *
     * @param  array<string, mixed>  $playlist  Spotify playlist data.
     * @param  string  $ownerId  Connected Spotify user ID.
     * @return bool Whether the playlist is owned by the connected account.
     */
    public function allowsPlaylist(array $playlist, string $ownerId): bool
    {
        $playlistOwnerId = data_get($playlist, 'owner.id');
        $allowedOwners = array_merge([$ownerId], config('services.spotify.import_allowed_owners', []));

        return is_string($playlistOwnerId) && in_array($playlistOwnerId, $allowedOwners, true);
    }

    /**
     * Determine whether an artist is excluded from Spotify imports.
     *
     * @param  array<string, mixed>  $artist  Spotify artist data.
     * @return bool Whether the artist should be skipped.
     */
    public function excludesArtist(array $artist): bool
    {
        $artistId = $artist['id'] ?? null;
        $artistName = $artist['name'] ?? null;
        $excluded = config('services.spotify.import_excluded_artists', []);
        $excludedNames = array_map(fn (string $name): string => $this->normalise($name), $excluded);

        return (is_string($artistId) && in_array($artistId, $excluded, true))
            || (is_string($artistName) && in_array($this->normalise($artistName), $excludedNames, true));
    }

    /**
     * Normalise an artist name for exact, case-insensitive exclusions.
     */
    private function normalise(string $value): string
    {
        return trim(mb_strtolower(preg_replace('/\s+/', ' ', $value) ?? $value));
    }
}
