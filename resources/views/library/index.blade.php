@extends('layouts.app', ['title' => 'Library · Effemeral'])

@section('content')
    <header class="flex flex-col gap-4 border-b border-ef-line pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold tracking-[0.24em] text-ef-accent-strong uppercase">Library / 03</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">The Working Library</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-ef-muted">Browse the Spotify material that supports album
                discovery, playlist auditing, and draft creation.</p>
        </div><span class="text-xs tracking-[0.16em] text-ef-muted">{{ number_format($spotifySavedAlbumCount) }} saved
            albums</span>
    </header>

    <div class="border border-ef-line bg-ef-surface p-6 sm:p-8">
        <form class="flex flex-col gap-3 sm:flex-row" method="GET" action="{{ route('library.index', [], false) }}">
            <label class="sr-only" for="library-search">Search library</label>
            <input id="library-search"
                class="min-w-0 flex-1 border border-ef-line bg-ef-canvas px-4 py-3 text-sm text-ef-text placeholder:text-ef-muted focus:border-ef-accent"
                type="search" name="library_search" value="{{ $librarySearch }}" placeholder="Search albums or artists">
            <button class="bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                type="submit">Search library</button>
            @if ($librarySearch !== '')
                <a class="border border-ef-line px-5 py-3 text-center text-sm font-semibold text-ef-accent-soft hover:border-ef-accent"
                    href="{{ route('library.index', [], false) }}">Clear</a>
            @endif
        </form>
    </div>

    <section class="border border-ef-line bg-ef-canvas p-6 sm:p-8" aria-labelledby="saved-albums-heading">
        <div class="flex flex-col gap-3 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Albums</p>
                <h2 id="saved-albums-heading" class="mt-2 text-2xl font-semibold">Saved Albums</h2>
            </div><span class="text-sm text-ef-muted">Showing up to 24 results</span>
        </div>
        <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($spotifySavedAlbums as $album)
                <li>
                    <article class="overflow-hidden border border-ef-line bg-ef-surface">
                        @if ($album->cover_image_url)
                            <img class="aspect-square w-full object-cover" src="{{ $album->cover_image_url }}"
                                alt="Cover art for {{ $album->displayTitle() }}">
                        @else
                            <div
                                class="flex aspect-square items-center justify-center bg-ef-surface-raised text-xs tracking-[0.12em] text-ef-muted">
                                No artwork</div>
                        @endif
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    @if ($album->canonical_album_id)
                                        <h3 class="truncate font-semibold">
                                            <a class="hover:text-ef-accent-strong"
                                                href="{{ route('albums.show', $album->canonical_album_id, false) }}">{{ $album->displayTitle() }}</a>
                                        </h3>
                                    @else
                                        <h3 class="truncate font-semibold">{{ $album->displayTitle() }}</h3>
                                    @endif
                                    <p class="mt-1 truncate text-sm text-ef-muted">
                                        @if ($album->canonical_artist_id)
                                            <a class="hover:text-ef-accent-strong"
                                                href="{{ route('artists.show', $album->canonical_artist_id, false) }}">{{ $album->primary_artist_name ?? 'Unknown artist' }}</a>
                                        @else
                                            {{ $album->primary_artist_name ?? 'Unknown artist' }}
                                        @endif
                                    </p>
                                </div>
                                @if ($album->spotify_url)
                                    <a class="shrink-0 text-xs font-semibold text-ef-accent-strong hover:underline"
                                        href="{{ $album->spotify_url }}" target="_blank" rel="noreferrer">Open in
                                        Spotify</a>
                                @endif
                            </div>
                            <p class="mt-4 text-xs tabular-nums text-ef-muted">{{ $album->total_tracks ?? 0 }} tracks ·
                                {{ $album->release_date ?: 'Release date unknown' }}</p>
                            @if ($album->completion_override !== null)
                                <p class="mt-2 text-xs text-ef-accent-strong">Manual:
                                    {{ $album->completion_override ? 'heard' : 'incomplete' }}</p>
                            @endif
                        </div>
                    </article>
                </li>
            @empty
                <li class="col-span-full border border-dashed border-ef-line p-6 text-sm text-ef-muted">No saved Spotify
                    albums have been imported yet.</li>
            @endforelse
        </ul>
    </section>

    <section class="border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="spotify-playlists-heading">
        <div class="flex flex-col gap-3 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Playlists</p>
                <h2 id="spotify-playlists-heading" class="mt-2 text-2xl font-semibold">Spotify Playlists</h2>
            </div><a class="text-sm font-semibold text-ef-accent-strong hover:underline"
                href="{{ route('spotify-playlists.index', [], false) }}">Browse All Spotify Playlists →</a>
        </div>
        <ul class="mt-6 grid gap-px border border-ef-line bg-ef-line sm:grid-cols-2">
            @forelse ($spotifyPlaylists as $playlist)
                <li class="bg-ef-canvas p-5">
                    <article>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold"><a class="hover:text-ef-accent-strong"
                                        href="{{ route('spotify-playlists.show', $playlist, false) }}">{{ $playlist->name }}</a>
                                </h3>
                                <p class="mt-2 text-sm text-ef-muted">{{ $playlist->items->count() }} tracks ·
                                    {{ $playlist->items->pluck('album_id')->filter()->unique()->count() }} albums</p>
                            </div>
                            @if ($playlist->spotify_url)
                                <a class="text-xs font-semibold text-ef-accent-strong hover:underline"
                                    href="{{ $playlist->spotify_url }}" target="_blank" rel="noreferrer">Open in
                                    Spotify</a>
                            @endif
                        </div>
                    </article>
                </li>
            @empty
                <li class="col-span-full bg-ef-canvas p-6 text-sm text-ef-muted">No Spotify playlists have been imported
                    yet.</li>
            @endforelse
        </ul>
    </section>
@endsection
