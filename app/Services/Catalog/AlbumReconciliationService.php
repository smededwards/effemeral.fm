<?php

namespace App\Services\Catalog;

use App\Models\Album;
use App\Models\CatalogEfmIdAlias;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds and safely merges duplicate album shells without guessing between releases.
 */
class AlbumReconciliationService
{
    /**
     * Find duplicate album-title groups and classify their merge safety.
     *
     * @param  int|null  $limit  Maximum number of groups to inspect.
     * @return Collection<int, array{artist_id: int, normalised_title: string, albums: EloquentCollection<int, Album>, safe: bool, reason: string|null}>
     */
    public function groups(?int $limit = null): Collection
    {
        $query = DB::table('albums as albums')
            ->join('album_artist', 'album_artist.album_id', '=', 'albums.id')
            ->select('album_artist.artist_id', 'albums.normalised_title')
            ->groupBy('album_artist.artist_id', 'albums.normalised_title')
            ->havingRaw('COUNT(DISTINCT albums.id) > 1')
            ->orderBy('album_artist.artist_id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(function (object $group): array {
            $albums = Album::query()
                ->with('artists')
                ->where('normalised_title', $group->normalised_title)
                ->whereHas('artists', fn ($query) => $query->whereKey($group->artist_id))
                ->orderBy('id')
                ->get();
            $reason = $this->unsafeReason($albums);

            return [
                'artist_id' => (int) $group->artist_id,
                'normalised_title' => (string) $group->normalised_title,
                'albums' => $albums,
                'safe' => $reason === null,
                'reason' => $reason,
            ];
        });
    }

    /**
     * Merge a previously classified safe group into its canonical album record.
     *
     * @param  Collection<int, Album>  $albums  Candidate duplicate album records.
     * @return int The number of retired album records.
     */
    public function merge(Collection $albums): int
    {
        $canonical = $this->canonicalAlbum($albums);
        if (! $canonical instanceof Album || $albums->count() < 2 || $this->unsafeReason($albums) !== null) {
            return 0;
        }

        return DB::transaction(function () use ($albums, $canonical): int {
            $retired = 0;

            foreach ($albums->reject(fn (Album $album): bool => $album->is($canonical)) as $duplicate) {
                CatalogEfmIdAlias::query()->create([
                    'entity_type' => 'album',
                    'retired_efm_id' => (string) $duplicate->getAttribute('efm_id'),
                    'canonical_entity_id' => $canonical->id,
                    'canonical_efm_id' => (string) $canonical->getAttribute('efm_id'),
                    'reason' => 'duplicate album record reconciled',
                ]);
                $this->moveReferences($duplicate->id, $canonical->id);
                $duplicate->artists()->detach();
                $duplicate->delete();
                $retired++;
            }

            return $retired;
        });
    }

    /**
     * Explain why a duplicate group must remain for review.
     *
     * @param  Collection<int, Album>  $albums  Candidate duplicate records.
     */
    private function unsafeReason(Collection $albums): ?string
    {
        $canonical = $this->canonicalAlbum($albums);
        if (! $canonical instanceof Album) {
            return 'canonical album unavailable';
        }

        $duplicates = $albums->reject(fn (Album $album): bool => $album->is($canonical));
        $providerAlbums = $albums->filter(fn (Album $album): bool => $this->hasProviderIdentity($album));

        if ($providerAlbums->count() > 1) {
            return 'contains multiple provider-backed records';
        }

        $duplicateIds = $duplicates->pluck('id')->all();
        if (DB::table('tracks')->whereIn('album_id', $duplicateIds)->exists()
            || DB::table('album_track_releases')->whereIn('album_id', $duplicateIds)->exists()) {
            return 'contains tracks';
        }

        if (DB::table('scrobbles')->whereIn('album_id', $duplicateIds)->exists()) {
            return 'contains listening history';
        }

        if (DB::table('spotify_liked_tracks')->whereIn('album_id', $duplicateIds)->exists()) {
            return 'contains liked-track references';
        }

        if (DB::table('album_recommendations')->whereIn('album_id', $duplicateIds)->exists()) {
            return 'contains recommendation references';
        }

        $canonicalArtistIds = $canonical->artists->modelKeys();
        $hasUnrepresentedArtist = $duplicates->contains(
            fn (Album $album): bool => array_diff($album->artists->modelKeys(), $canonicalArtistIds) !== [],
        );

        if ($hasUnrepresentedArtist) {
            return 'artist credits differ';
        }

        return $providerAlbums->isNotEmpty() && ! $this->hasProviderIdentity($canonical)
            ? 'provider record could not be selected'
            : null;
    }

    /**
     * Prefer an existing provider-backed album as the canonical record.
     *
     * @param  Collection<int, Album>  $albums  Candidate album records.
     */
    private function canonicalAlbum(Collection $albums): ?Album
    {
        return $albums
            ->sortBy(fn (Album $album): array => [
                $this->hasProviderIdentity($album) ? 0 : 1,
                $album->id,
            ])
            ->first();
    }

    /**
     * Determine whether an album has a source identity that should survive a merge.
     */
    private function hasProviderIdentity(Album $album): bool
    {
        return $album->spotify_id !== null
            || $album->lastfm_mbid !== null
            || $album->musicbrainz_release_group_mbid !== null;
    }

    /**
     * Move references that can safely point to the surviving album.
     */
    private function moveReferences(int $duplicateId, int $canonicalId): void
    {
        DB::table('album_artist')->insertOrIgnore(
            DB::table('album_artist')
                ->where('album_id', $duplicateId)
                ->get()
                ->map(fn (object $pivot): array => [
                    'album_id' => $canonicalId,
                    'artist_id' => $pivot->artist_id,
                    'artist_position' => $pivot->artist_position,
                ])
                ->all(),
        );
        DB::table('album_playlist_items')->insertOrIgnore(
            DB::table('album_playlist_items')
                ->where('album_id', $duplicateId)
                ->get()
                ->map(fn (object $pivot): array => [
                    'album_playlist_id' => $pivot->album_playlist_id,
                    'album_id' => $canonicalId,
                    'position' => $pivot->position,
                    'created_at' => $pivot->created_at,
                    'updated_at' => $pivot->updated_at,
                ])
                ->all(),
        );
        DB::table('spotify_album_collections')->insertOrIgnore(
            DB::table('spotify_album_collections')
                ->where('album_id', $duplicateId)
                ->get()
                ->map(fn (object $collection): array => [
                    'album_id' => $canonicalId,
                    'collection_type' => $collection->collection_type,
                    'spotify_playlist_id' => $collection->spotify_playlist_id,
                    'saved_at' => $collection->saved_at,
                    'created_at' => $collection->created_at,
                    'updated_at' => $collection->updated_at,
                ])
                ->all(),
        );
        DB::table('spotify_playlist_items')->where('album_id', $duplicateId)->update(['album_id' => $canonicalId]);
        DB::table('album_artist')->where('album_id', $duplicateId)->delete();
        DB::table('album_playlist_items')->where('album_id', $duplicateId)->delete();
        DB::table('spotify_album_collections')->where('album_id', $duplicateId)->delete();
    }
}
