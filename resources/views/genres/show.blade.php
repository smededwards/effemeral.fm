@extends('layouts.app', ['title' => $genre->name . ' · Genre · Effemeral'])

@section('content')
    <header class="border-b border-ef-line pb-8">
        <a class="text-sm font-semibold text-ef-accent-strong hover:underline"
            href="{{ route('catalogue.index', [], false) }}">← Catalogue</a>
        <p class="mt-5 text-xs font-semibold tracking-[0.2em] text-ef-accent-strong uppercase">Genre</p>
        <h1 class="mt-2 font-serif text-4xl font-semibold sm:text-5xl">{{ $genre->name }}</h1>
        <p class="mt-3 text-sm text-ef-muted">Artists and albums in your catalogue tagged with this genre.</p>
    </header>

    <section class="border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="genre-artists">
        <div class="flex items-end justify-between gap-3 border-b border-ef-line pb-5">
            <h2 id="genre-artists" class="text-2xl font-semibold">Artists</h2>
            <span class="text-sm text-ef-muted">{{ number_format($artists->total()) }}</span>
        </div>
        @if ($artists->isNotEmpty())
            <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($artists as $artist)
                    <li>
                        <a class="block border border-ef-line bg-ef-canvas p-5 hover:border-ef-accent"
                            href="{{ route('artists.show', $artist, false) }}">
                            <h3 class="font-semibold">{{ $artist->name }}</h3>
                            <p class="mt-2 text-sm text-ef-muted">{{ $artist->albums_count }}
                                {{ $artist->albums_count === 1 ? 'album' : 'albums' }}</p>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-5">{{ $artists->links() }}</div>
        @else
            <p class="mt-5 text-sm text-ef-muted">No artists are linked to this genre yet.</p>
        @endif
    </section>

    <section class="border border-ef-line bg-ef-canvas p-6 sm:p-8" aria-labelledby="genre-albums">
        <div class="flex items-end justify-between gap-3 border-b border-ef-line pb-5">
            <h2 id="genre-albums" class="text-2xl font-semibold">Albums</h2>
            <span class="text-sm text-ef-muted">{{ number_format($albums->total()) }}</span>
        </div>
        @if ($albums->isNotEmpty())
            <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($albums as $album)
                    <li>
                        <a class="block border border-ef-line bg-ef-surface p-5 hover:border-ef-accent"
                            href="{{ route('albums.show', $album, false) }}">
                            <h3 class="font-semibold">{{ $album->displayTitle() }}</h3>
                            <p class="mt-2 text-sm text-ef-muted">
                                {{ $album->artists->pluck('name')->implode(', ') ?: 'Unknown artist' }}</p>
                            <p class="mt-2 text-sm text-ef-muted">{{ $album->tracks_count }} tracks</p>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-5">{{ $albums->links() }}</div>
        @else
            <p class="mt-5 text-sm text-ef-muted">No albums are linked to this genre yet.</p>
        @endif
    </section>
@endsection
