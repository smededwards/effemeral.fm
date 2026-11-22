@extends('layouts.app', ['title' => 'Catalogue · Effemeral'])

@section('content')
        <header>
                <p class="text-xs font-semibold tracking-[0.24em] text-ef-accent-strong uppercase">Catalogue / 05</p>
                <h1 class="mt-3 text-4xl font-semibold">Catalogue</h1>
                <p class="mt-3 max-w-2xl text-ef-muted">Choose a canonical artist or album to use as the starting point for discovery.</p>
            </header>

            <form class="rounded-xl border border-ef-line bg-ef-surface p-6" method="GET" action="{{ route('catalogue.index', [], false) }}">
                <label class="block text-sm font-medium text-ef-text" for="catalogue-search">Search artists and albums</label>
                <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                    <input id="catalogue-search" class="block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text" type="search" name="q" value="{{ $search }}" placeholder="Cult of Luna, Mariner..." autofocus>
                    <button class="rounded-md bg-ef-accent px-4 py-2 font-medium text-ef-accent-ink hover:bg-ef-accent-hover" type="submit">Search</button>
                </div>
            </form>

            @if ($selectedArtist)
                <section class="rounded-xl border border-ef-accent-line bg-ef-accent-wash/30 p-6">
                    <p class="text-xs tracking-[0.2em] text-ef-accent-strong uppercase">Selected artist</p>
                    <h2 class="mt-2 text-2xl font-semibold">{{ $selectedArtist->name }}</h2>
                    <p class="mt-2 text-sm text-ef-muted">{{ $selectedArtist->albums->count() }} canonical albums</p>
                    @if ($selectedArtist->albums->isNotEmpty())
                        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($selectedArtist->albums as $album)
                                <a class="rounded-lg border border-ef-line bg-ef-canvas p-4 hover:border-ef-accent" href="{{ route('albums.show', $album, false) }}">
                                    <h3 class="font-medium">{{ $album->title }}</h3>
                                    <p class="mt-1 text-sm text-ef-muted">{{ $album->tracks_count }} tracks</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-5 text-sm text-ef-muted">No canonical albums are linked to this artist yet.</p>
                    @endif
                    <form class="mt-6 border-t border-ef-accent-line pt-6" method="POST" action="{{ route('catalogue.discover', [], false) }}">
                        @csrf
                        <input type="hidden" name="artist_id" value="{{ $selectedArtist->id }}">
                        <p class="text-sm font-medium text-ef-text">Find incomplete albums in this style</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="block text-sm text-ef-muted">Genre
                                <input class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text" type="text" name="genre" value="{{ old('genre', $selectedArtist->acceptedGenres->first()?->name) }}" placeholder="post-metal" required>
                            </label>
                            <label class="block text-sm text-ef-muted">Release year
                                <input class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text" type="number" name="year" value="{{ old('year', now()->year) }}" min="1900" max="2100" required>
                            </label>
                        </div>
                        <button class="mt-4 rounded-md bg-ef-accent px-4 py-2 font-medium text-ef-accent-ink hover:bg-ef-accent-soft" type="submit">Generate suggestions</button>
                    </form>
                    <form class="mt-4" method="POST" action="{{ route('catalogue.discography', [], false) }}">
                        @csrf
                        <input type="hidden" name="artist_id" value="{{ $selectedArtist->id }}">
                        <label class="mt-4 flex items-start gap-2 text-sm text-ef-muted">
                            <input class="mt-1" type="checkbox" name="include_non_albums" value="1">
                            <span>Include singles and other non-album releases</span>
                        </label>
                        <button class="rounded-md border border-ef-accent px-4 py-2 font-medium text-ef-accent-strong hover:border-ef-accent-hover" type="submit">Generate discography draft</button>
                    </form>
                </section>
            @endif

            @if ($selectedAlbum)
                <section class="rounded-xl border border-ef-accent-line bg-ef-accent-wash/30 p-6">
                    <p class="text-xs tracking-[0.2em] text-ef-accent-strong uppercase">Selected album</p>
                    <h2 class="mt-2 text-2xl font-semibold">{{ $selectedAlbum->title }}</h2>
                    <p class="mt-2 flex flex-wrap gap-x-2 text-ef-muted">
                        @forelse ($selectedAlbum->artists as $artist)
                            @if (! $loop->first)<span aria-hidden="true">·</span>@endif
                            <a class="hover:text-ef-accent-strong" href="{{ route('artists.show', $artist, false) }}">{{ $artist->name }}</a>
                        @empty
                            <span>Unknown artist</span>
                        @endforelse
                    </p>
                    <p class="mt-2 text-sm text-ef-muted">{{ $selectedAlbum->tracks->count() }} canonical tracks{{ $selectedAlbum->release_date ? ' · '.$selectedAlbum->release_date : '' }}</p>
                    <p class="mt-5 text-sm text-ef-muted">This album is ready to become the context for genre, year, and completion-aware discovery.</p>
                    <form class="mt-6 border-t border-ef-accent-line pt-6" method="POST" action="{{ route('catalogue.discover', [], false) }}">
                        @csrf
                        <input type="hidden" name="album_id" value="{{ $selectedAlbum->id }}">
                        <p class="text-sm font-medium text-ef-text">Find incomplete albums in this style</p>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="block text-sm text-ef-muted">Genre
                                <input class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text" type="text" name="genre" value="{{ old('genre', $selectedAlbum->artists->flatMap->acceptedGenres->first()?->name) }}" placeholder="post-metal" required>
                            </label>
                            <label class="block text-sm text-ef-muted">Release year
                                <input class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text" type="number" name="year" value="{{ old('year', substr((string) $selectedAlbum->release_date, 0, 4) ?: now()->year) }}" min="1900" max="2100" required>
                            </label>
                        </div>
                        <button class="mt-4 rounded-md bg-ef-accent px-4 py-2 font-medium text-ef-accent-ink hover:bg-ef-accent-soft" type="submit">Generate suggestions</button>
                    </form>
                </section>
            @endif

            @if ($search !== '')
                <div class="grid gap-6 md:grid-cols-2">
                    <section class="rounded-xl border border-ef-line bg-ef-surface p-6">
                        <h2 class="text-xl font-medium">Artists</h2>
                        @if ($artists->isNotEmpty())
                            <div class="mt-5 space-y-3">
                                @foreach ($artists as $artist)
                                    <a class="block rounded-lg border border-ef-line bg-ef-canvas p-4 hover:border-ef-accent" href="{{ route('artists.show', $artist, false) }}">
                                        <h3 class="font-medium">{{ $artist->name }}</h3>
                                        <p class="mt-1 text-sm text-ef-muted">{{ $artist->albums_count }} canonical albums</p>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-5 text-sm text-ef-muted">No matching artists.</p>
                        @endif
                    </section>

                    <section class="rounded-xl border border-ef-line bg-ef-surface p-6">
                        <h2 class="text-xl font-medium">Albums</h2>
                        @if ($albums->isNotEmpty())
                            <div class="mt-5 space-y-3">
                                @foreach ($albums as $album)
                                    <a class="block rounded-lg border border-ef-line bg-ef-canvas p-4 hover:border-ef-accent" href="{{ route('albums.show', $album, false) }}">
                                        <h3 class="font-medium">{{ $album->title }}</h3>
                                        <p class="mt-1 text-sm text-ef-muted">{{ $album->artists->pluck('name')->implode(', ') ?: 'Unknown artist' }}</p>
                                        <p class="mt-1 text-sm text-ef-muted">{{ $album->tracks_count }} canonical tracks</p>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-5 text-sm text-ef-muted">No matching albums.</p>
                        @endif
                    </section>
                </div>
            @else
                <p class="rounded-xl border border-dashed border-ef-line p-8 text-center text-sm text-ef-muted">Search the catalogue to choose an artist or album.</p>
            @endif
@endsection
