@extends('layouts.app', ['title' => $spotifyPlaylist->name.' · Spotify Playlist · Effemeral'])

@section('content')
    <header class="flex flex-col gap-6 border-b border-ef-line pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a class="text-sm font-semibold text-ef-accent-strong hover:underline" href="{{ route('playlists.index', [], false) }}">← All Playlists</a>
            <p class="mt-5 text-xs font-semibold tracking-[0.2em] text-ef-accent-strong uppercase">Imported Spotify Playlist</p>
            <h1 class="mt-2 font-serif text-4xl font-semibold sm:text-5xl">{{ $spotifyPlaylist->name }}</h1>
            <p class="mt-3 text-sm text-ef-muted">
                {{ number_format($spotifyPlaylist->tracks_total) }} Spotify tracks · {{ number_format($matchedAlbumCount) }} matched albums
                @if ($spotifyPlaylist->public === true) · Public @elseif ($spotifyPlaylist->public === false) · Private @endif
                @if ($spotifyPlaylist->collaborative) · Collaborative @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-4">
            <a class="text-sm font-semibold text-ef-accent-strong hover:underline" href="{{ route('recommendations.audit', ['playlist' => $spotifyPlaylist->id], false) }}">Audit Coverage</a>
            @if ($spotifyPlaylist->spotify_url)
                <a class="text-sm font-semibold text-ef-accent-strong hover:underline" href="{{ $spotifyPlaylist->spotify_url }}" target="_blank" rel="noreferrer">Open in Spotify</a>
            @endif
        </div>
    </header>

    @if ($spotifyPlaylist->description)
        <p class="max-w-3xl text-sm leading-6 text-ef-muted">{{ $spotifyPlaylist->description }}</p>
    @endif

    @if ($spotifyPlaylist->last_import_error)
        <p class="border-l-4 border-red-700 bg-ef-surface px-4 py-3 text-sm text-red-200" role="alert">{{ $spotifyPlaylist->last_import_error }}</p>
    @endif

    <section class="border border-ef-line bg-ef-canvas p-6 sm:p-8" aria-labelledby="spotify-track-list">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-ef-line pb-5">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Playlist Contents</p>
                <h2 id="spotify-track-list" class="mt-2 text-2xl font-semibold">Tracks in Spotify Order</h2>
            </div>
            <p class="text-sm text-ef-muted">{{ number_format($playlistItems->total()) }} imported tracks</p>
        </div>
        @if ($playlistItems->isNotEmpty())
            <ol class="mt-3 divide-y divide-ef-line">
                @foreach ($playlistItems as $item)
                    <li class="flex items-start gap-4 py-4">
                        <span class="w-10 shrink-0 pt-0.5 text-right text-sm tabular-nums text-ef-muted">{{ $item->position + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            @if ($item->track)
                                <p class="font-medium">{{ $item->track->title }}</p>
                            @else
                                <p class="font-medium">{{ $item->track_name ?: 'Unavailable track' }}</p>
                            @endif
                            @if ($item->album)
                                <p class="mt-1 text-sm text-ef-muted">
                                    <a class="hover:text-ef-accent-strong" href="{{ route('albums.show', $item->album, false) }}">{{ $item->album->title }}</a>
                                    @if ($item->album->artists->isNotEmpty())
                                        · @foreach ($item->album->artists as $artist)
                                            @if (! $loop->first), @endif<a class="hover:text-ef-accent-strong" href="{{ route('artists.show', $artist, false) }}">{{ $artist->name }}</a>
                                        @endforeach
                                    @endif
                                </p>
                            @else
                                <p class="mt-1 text-sm text-ef-muted">Album not matched in the catalogue</p>
                            @endif
                        </div>
                        @if ($item->spotify_track_id)
                            <a class="shrink-0 text-xs font-semibold text-ef-accent-strong hover:underline" href="https://open.spotify.com/track/{{ $item->spotify_track_id }}" target="_blank" rel="noreferrer">Open Track</a>
                        @endif
                    </li>
                @endforeach
            </ol>
            @if ($playlistItems->hasPages())
                <div class="mt-6 border-t border-ef-line pt-5">{{ $playlistItems->links() }}</div>
            @endif
        @else
            <p class="mt-5 text-sm text-ef-muted">No playlist tracks have been imported yet.</p>
        @endif
    </section>
@endsection
