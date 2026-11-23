@extends('layouts.app', ['title' => $album->displayTitle() . ' · Album · Effemeral'])

@section('content')
    <header class="border-b border-ef-line pb-8">
        <a class="text-sm font-semibold text-ef-accent-strong hover:underline"
            href="{{ route('catalogue.index', [], false) }}">← Catalogue</a>
        <div class="mt-5 flex flex-col gap-6 sm:flex-row sm:items-end">
            @if ($album->cover_image_url)
                <img class="h-36 w-36 border border-ef-line object-cover" src="{{ $album->cover_image_url }}"
                    alt="Cover art for {{ $album->displayTitle() }}">
            @endif
            <div>
                <p class="text-xs font-semibold tracking-[0.2em] text-ef-accent-strong uppercase">Album</p>
                <h1 class="mt-2 font-serif text-4xl font-semibold sm:text-5xl">{{ $album->displayTitle() }}</h1>
                <p class="mt-3 flex flex-wrap gap-x-2 text-base text-ef-muted">
                    @forelse ($album->artists as $artist)
                        @if (!$loop->first)
                            <span aria-hidden="true">·</span>
                        @endif
                        <a class="hover:text-ef-accent-strong"
                            href="{{ route('artists.show', $artist, false) }}">{{ $artist->name }}</a>
                    @empty
                        <span>Unknown artist</span>
                    @endforelse
                </p>
                <p class="mt-3 text-sm text-ef-muted">{{ $album->release_date ?: 'Release date unknown' }} ·
                    {{ $album->tracks->count() }} of {{ $album->total_tracks ?? $album->tracks->count() }} tracks</p>
                @if ($album->spotify_url)
                    <a class="mt-3 inline-block text-sm font-semibold text-ef-accent-strong hover:underline"
                        href="{{ $album->spotify_url }}" target="_blank" rel="noreferrer">Open in Spotify</a>
                @endif
            </div>
        </div>
    </header>

    <section class="border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="album-genres">
        <h2 id="album-genres" class="text-2xl font-semibold">Genres</h2>
        @php($genres = $album->artists->flatMap->acceptedGenres->unique('id')->sortBy('name'))
        @if ($genres->isNotEmpty())
            <ul class="mt-4 flex flex-wrap gap-2">
                @foreach ($genres as $genre)
                    <li><a class="inline-block border border-ef-accent-line bg-ef-canvas px-3 py-2 text-sm text-ef-accent-soft hover:border-ef-accent hover:text-ef-text"
                            href="{{ route('genres.show', $genre, false) }}">{{ $genre->name }}</a></li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 text-sm text-ef-muted">No accepted genres are recorded for the album’s artists yet.</p>
        @endif
    </section>

    <section class="border border-ef-line bg-ef-canvas p-6 sm:p-8" aria-labelledby="album-tracks">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-ef-line pb-5">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Track Listing</p>
                <h2 id="album-tracks" class="mt-2 text-2xl font-semibold">Tracks</h2>
            </div>
            @if ($album->spotify_id)
                <span class="text-sm text-ef-muted">{{ $album->album_type ? ucfirst($album->album_type) : 'Album' }}</span>
            @endif
        </div>
        @if ($album->tracks->isNotEmpty())
            <ol class="mt-3 divide-y divide-ef-line">
                @foreach ($album->tracks as $track)
                    <li class="flex items-center gap-4 py-3">
                        <span
                            class="w-10 shrink-0 text-right text-sm tabular-nums text-ef-muted">{{ $track->disc_number && $track->disc_number > 1 ? $track->disc_number . '–' : '' }}{{ $track->track_number ?? $loop->iteration }}</span>
                        <span class="min-w-0 flex-1 font-medium">{{ $track->title }}</span>
                        @if ($track->duration_ms)
                            <span
                                class="shrink-0 text-sm tabular-nums text-ef-muted">{{ gmdate('i:s', (int) ($track->duration_ms / 1000)) }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        @else
            <p class="mt-5 text-sm text-ef-muted">Track details haven’t been imported for this album yet.</p>
        @endif
    </section>

    <section class="grid gap-6 lg:grid-cols-2" aria-label="Playlist associations">
        <div class="border border-ef-line bg-ef-surface p-6 sm:p-8">
            <h2 class="text-2xl font-semibold">Effemeral Playlists</h2>
            @if ($album->playlists->isNotEmpty())
                <ul class="mt-4 divide-y divide-ef-line">
                    @foreach ($album->playlists as $playlist)
                        <li class="py-3"><a class="font-semibold text-ef-accent-strong hover:underline"
                                href="{{ route('album-playlists.show', $playlist, false) }}">{{ $playlist->name }}</a>
                            <p class="mt-1 text-xs text-ef-muted">
                                {{ ucfirst(str_replace('_', ' ', $playlist->spotify_sync_status)) }}</p>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 text-sm text-ef-muted">This album isn’t in an Effemeral playlist yet.</p>
            @endif
        </div>
        <div class="border border-ef-line bg-ef-surface p-6 sm:p-8">
            <h2 class="text-2xl font-semibold">Spotify Playlists</h2>
            @php($spotifyPlaylists = $album->spotifyPlaylistItems->pluck('playlist')->filter()->unique('id')->sortBy('name'))
            @if ($spotifyPlaylists->isNotEmpty())
                <ul class="mt-4 divide-y divide-ef-line">
                    @foreach ($spotifyPlaylists as $playlist)
                        <li class="py-3"><a class="font-semibold text-ef-accent-strong hover:underline"
                                href="{{ route('spotify-playlists.show', $playlist, false) }}">{{ $playlist->name }}</a>
                            <p class="mt-1 text-xs text-ef-muted">Imported from Spotify</p>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-3 text-sm text-ef-muted">This album hasn’t been matched in an imported Spotify playlist.</p>
            @endif
        </div>
    </section>
@endsection
