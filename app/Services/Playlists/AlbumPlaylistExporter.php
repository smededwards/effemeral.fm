<?php

namespace App\Services\Playlists;

use App\Models\Album;
use App\Models\AlbumPlaylist;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports an Effemeral album playlist as a CSV download.
 */
class AlbumPlaylistExporter
{
    /**
     * @return StreamedResponse The CSV download response.
     */
    public function download(AlbumPlaylist $albumPlaylist): StreamedResponse
    {
        $albumPlaylist->load('albums.artists.acceptedGenres');
        /** @var Collection<int, Album> $albums */
        $albums = $albumPlaylist->albums;

        return response()->streamDownload(function () use ($albums): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Position', 'Album', 'Artist', 'Release date', 'Styles', 'Spotify URL']);

            foreach ($albums as $position => $album) {
                /** @var Collection<int, Artist> $artists */
                $artists = $album->artists;
                fputcsv($output, [
                    $position + 1,
                    $album->displayTitle(),
                    $artists->pluck('name')->implode(', ') ?: 'Unknown artist',
                    $album->release_date ?: '',
                    $artists->flatMap(fn (Artist $artist) => $artist->acceptedGenres->pluck('name'))->unique()->sort()->implode(', '),
                    $album->spotify_url ?: '',
                ]);
            }

            fclose($output);
        }, str()->slug($albumPlaylist->name).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
