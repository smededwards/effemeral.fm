@extends('layouts.app', ['title' => $artist->name.' · Artist · Effemeral'])

@section('content')
    <header class="border-b border-ef-line pb-8">
        <a class="text-sm font-semibold text-ef-accent-strong hover:underline" href="{{ route('catalogue.index', [], false) }}">← Catalogue</a>
        <p class="mt-5 text-xs font-semibold tracking-[0.2em] text-ef-accent-strong uppercase">Artist</p>
        <h1 class="mt-2 font-serif text-4xl font-semibold sm:text-5xl">{{ $artist->name }}</h1>
        @if ($artist->spotify_id)
            <a class="mt-4 inline-block text-sm font-semibold text-ef-accent-strong hover:underline" href="https://open.spotify.com/artist/{{ $artist->spotify_id }}" target="_blank" rel="noreferrer">Open in Spotify</a>
        @endif
    </header>

    <section class="border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="artist-genres">
        <h2 id="artist-genres" class="text-2xl font-semibold">Genres</h2>
        @if ($artist->acceptedGenres->isNotEmpty())
            <ul class="mt-4 flex flex-wrap gap-2">
                @foreach ($artist->acceptedGenres->sortBy('name') as $genre)
                    <li><a class="inline-block border border-ef-accent-line bg-ef-canvas px-3 py-2 text-sm text-ef-accent-soft hover:border-ef-accent hover:text-ef-text" href="{{ route('genres.show', $genre, false) }}">{{ $genre->name }}</a></li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 text-sm text-ef-muted">No accepted genres are recorded for this artist yet.</p>
        @endif
    </section>

    <section class="border border-ef-line bg-ef-canvas p-6 sm:p-8" aria-labelledby="artist-albums">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-ef-line pb-5">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Discography</p>
                <h2 id="artist-albums" class="mt-2 text-2xl font-semibold">Albums</h2>
            </div>
            <span class="text-sm text-ef-muted">{{ $artist->albums->count() }} {{ $artist->albums->count() === 1 ? 'album' : 'albums' }}</span>
        </div>
        @if ($artist->albums->isNotEmpty())
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($artist->albums as $album)
                    <a class="border border-ef-line bg-ef-surface p-5 transition hover:border-ef-accent" href="{{ route('albums.show', $album, false) }}">
                        <h3 class="font-semibold">{{ $album->title }}</h3>
                        <p class="mt-2 text-sm text-ef-muted">{{ $album->release_date ?: 'Release date unknown' }} · {{ $album->tracks_count }} tracks</p>
                        <span class="mt-4 inline-block text-sm font-semibold text-ef-accent-strong">View Album →</span>
                    </a>
                @endforeach
            </div>
        @else
            <p class="mt-5 text-sm text-ef-muted">No canonical albums are linked to this artist yet.</p>
        @endif
    </section>
@endsection
