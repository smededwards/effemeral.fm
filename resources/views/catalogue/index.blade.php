@extends('layouts.app', ['title' => 'Catalogue · Effemeral'])

@section('content')
    <header>
        <p class="text-xs font-semibold tracking-[0.24em] text-ef-accent-strong uppercase">Catalogue / 05</p>
        <h1 class="mt-3 text-4xl font-semibold">Catalogue</h1>
        <p class="mt-3 max-w-2xl text-ef-muted">Choose a canonical artist or album to use as the starting point for
            discovery.</p>
        <a class="mt-4 inline-block text-sm font-semibold text-ef-accent-strong hover:underline"
            href="{{ route('catalogue.styles', [], false) }}">Browse styles →</a>
    </header>

    <form class="rounded-xl border border-ef-line bg-ef-surface p-6" method="GET"
        action="{{ route('catalogue.index', [], false) }}">
        <label class="block text-sm font-medium text-ef-text" for="catalogue-search">Search artists and albums</label>
        <div class="mt-3 flex flex-col gap-3 sm:flex-row">
            <input id="catalogue-search" class="block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text"
                type="search" name="q" value="{{ $search }}" placeholder="Cult of Luna, Mariner..." autofocus>
            <button class="rounded-md bg-ef-accent px-4 py-2 font-medium text-ef-accent-ink hover:bg-ef-accent-hover"
                type="submit">Search</button>
        </div>
    </form>

    <section class="rounded-xl border border-ef-line bg-ef-surface p-6" aria-labelledby="catalogue-filters-heading">
        <h2 id="catalogue-filters-heading" class="text-xl font-semibold">Find albums to add</h2>
        <p class="mt-2 text-sm text-ef-muted">Filters search only albums already in your canonical catalogue. Completed
            albums are hidden by default.</p>
        <form class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" method="GET"
            action="{{ route('catalogue.index', [], false) }}">
            @if ($search !== '')
                <input type="hidden" name="q" value="{{ $search }}">
            @endif
            <input type="hidden" name="filter" value="1">
            <label class="text-sm text-ef-muted">Genre
                <select class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text" name="genre">
                    <option value="">All genres</option>
                    @foreach ($catalogueGenres as $genre)
                        <option value="{{ $genre->id }}" @selected((string) ($filters['genre'] ?? '') === (string) $genre->id)>{{ $genre->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-ef-muted">Country
                <select class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text" name="country">
                    <option value="">All countries</option>
                    @foreach ($catalogueCountries as $countryCode)
                        <option value="{{ $countryCode }}" @selected(($filters['country'] ?? '') === $countryCode)>
                            {{ config('countries.' . $countryCode, $countryCode) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-ef-muted">Release year
                <input class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text" type="number"
                    name="year" min="1900" max="2100" value="{{ $filters['year'] ?? '' }}">
            </label>
            <label class="text-sm text-ef-muted">Listening completion
                <select class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text" name="completion">
                    <option value="">Not completed yet</option>
                    <option value="incomplete" @selected(($filters['completion'] ?? '') === 'incomplete')>Not started</option>
                    <option value="partial" @selected(($filters['completion'] ?? '') === 'partial')>In progress</option>
                    <option value="complete" @selected(($filters['completion'] ?? '') === 'complete')>Completed</option>
                </select>
            </label>
            <label class="flex items-center gap-2 text-sm text-ef-muted">
                <input type="checkbox" name="include_completed" value="1" @checked(request()->boolean('include_completed'))>
                <span>Include completed alongside other results</span>
            </label>
            <label class="flex items-center gap-2 text-sm text-ef-muted">
                <input type="checkbox" name="include_non_albums" value="1" @checked(!empty($filters['include_non_albums']))>
                <span>Include singles and other non-album releases</span>
            </label>
            <div class="sm:col-span-2 lg:col-span-3">
                <button class="rounded-md bg-ef-accent px-4 py-2 font-medium text-ef-accent-ink hover:bg-ef-accent-hover"
                    type="submit">Apply filters</button>
            </div>
        </form>
    </section>

    @if ($filteredAlbums instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <section class="rounded-xl border border-ef-line bg-ef-surface p-6" aria-labelledby="filtered-albums-heading">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-ef-line pb-4">
                <div>
                    <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Canonical catalogue
                    </p>
                    <h2 id="filtered-albums-heading" class="mt-2 text-2xl font-semibold">Matching albums</h2>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-ef-muted">{{ number_format($filteredAlbums->total()) }} matches</span>
                    <a class="text-sm font-semibold text-ef-accent-strong hover:underline"
                        href="{{ route('catalogue.index', $search === '' ? [] : ['q' => $search], false) }}">{{ 'Clear filters' }}</a>
                </div>
            </div>
            @if ($filteredAlbums->isNotEmpty())
                <form class="mt-5" method="POST" action="{{ route('album-playlists.albums.bulk-store', [], false) }}">
                    @csrf
                    <div class="mb-5 grid gap-3 rounded-lg border border-ef-line p-4 sm:grid-cols-3">
                        <label class="text-sm text-ef-muted">Destination
                            <select class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text"
                                name="destination">
                                <option value="existing" @selected($albumPlaylists->isNotEmpty())>Add to an existing Effemeral playlist
                                </option>
                                <option value="new" @selected($albumPlaylists->isEmpty())>Create a new Effemeral playlist</option>
                            </select>
                        </label>
                        <label class="text-sm text-ef-muted">Existing playlist
                            <select class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text"
                                name="playlist_id">
                                <option value="">Choose playlist</option>
                                @foreach ($albumPlaylists as $playlist)
                                    <option value="{{ $playlist->id }}">{{ $playlist->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm text-ef-muted">New playlist name
                            <input class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text"
                                type="text" name="playlist_name" maxlength="100" placeholder="e.g. Fossil Gardens">
                        </label>
                    </div>
                    <p class="mb-3 text-sm text-ef-muted">Select up to 50 albums on this page. Adding them updates the
                        Effemeral draft; Spotify is changed only after you review and confirm a sync.</p>
                    <label class="mb-4 flex items-center gap-2 text-sm font-semibold text-ef-text" for="catalogue-select-all">
                        <input id="catalogue-select-all" type="checkbox">
                        <span>Select all albums on this page</span>
                    </label>
                    <ul class="space-y-3">
                        @foreach ($filteredAlbums as $album)
                            <li class="flex gap-4 rounded-lg border border-ef-line bg-ef-canvas p-4 hover:border-ef-accent">
                                <input class="catalogue-album-checkbox mt-1" id="catalogue-album-{{ $album->id }}" type="checkbox"
                                    name="album_ids[]" value="{{ $album->id }}">
                                <label class="min-w-0 flex-1 cursor-pointer" for="catalogue-album-{{ $album->id }}">
                                    <span class="block font-semibold">{{ $album->displayTitle() }}</span>
                                    <span
                                        class="mt-1 block text-sm text-ef-muted">{{ $album->artists->pluck('name')->implode(', ') ?: 'Unknown artist' }}
                                        · {{ $album->release_date ?: 'Release year unknown' }}</span>
                                    <span
                                        class="mt-1 block text-sm text-ef-muted">{{ $album->artists->flatMap->acceptedGenres->pluck('name')->unique()->implode(', ') ?: 'No genres recorded' }}
                                        · {{ $album->completion_summary }}</span>
                                    <span
                                        class="mt-1 block text-xs font-semibold text-ef-accent-strong uppercase">{{ $album->completion_status }}</span>
                                </label>
                                <a class="self-center text-sm font-semibold text-ef-accent-strong hover:underline"
                                    href="{{ route('albums.show', $album, false) }}">View album</a>
                            </li>
                        @endforeach
                    </ul>
                    <button
                        class="mt-5 rounded-md bg-ef-accent px-4 py-2 font-medium text-ef-accent-ink hover:bg-ef-accent-hover"
                        type="submit">Add selected albums</button>
                    <div class="mt-5">{{ $filteredAlbums->links() }}</div>
                </form>
            @else
                <p class="mt-5 text-sm text-ef-muted">No catalogue albums match these filters. Adjust the filters or import
                    more catalogue data.</p>
            @endif
        </section>
    @endif

    @if ($selectedArtist)
        <section class="rounded-xl border border-ef-accent-line bg-ef-accent-wash/30 p-6"
            aria-labelledby="selected-artist-heading">
            <p class="text-xs tracking-[0.2em] text-ef-accent-strong uppercase">Selected artist</p>
            <h2 id="selected-artist-heading" class="mt-2 text-2xl font-semibold">{{ $selectedArtist->name }}</h2>
            <p class="mt-2 text-sm text-ef-muted">{{ $selectedArtist->albums->count() }} canonical albums</p>
            @if ($selectedArtist->albums->isNotEmpty())
                <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($selectedArtist->albums as $album)
                        <li>
                            <a class="block rounded-lg border border-ef-line bg-ef-canvas p-4 hover:border-ef-accent"
                                href="{{ route('albums.show', $album, false) }}">
                                <h3 class="font-medium">{{ $album->displayTitle() }}</h3>
                                <p class="mt-1 text-sm text-ef-muted">{{ $album->tracks_count }} tracks</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-5 text-sm text-ef-muted">No canonical albums are linked to this artist yet.</p>
            @endif
            <form class="mt-6 border-t border-ef-accent-line pt-6" method="POST"
                action="{{ route('catalogue.discover', [], false) }}">
                @csrf
                <input type="hidden" name="artist_id" value="{{ $selectedArtist->id }}">
                <p class="text-sm font-medium text-ef-text">Find incomplete albums in this style</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm text-ef-muted">Genre
                        <select class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text" name="genre"
                            required>
                            <option value="">Choose a style</option>
                            @foreach ($catalogueGenres as $genre)
                                <option value="{{ $genre->normalised_name }}" @selected(old('genre', $selectedArtist->acceptedGenres->first()?->normalised_name) === $genre->normalised_name)>
                                    {{ $genre->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm text-ef-muted">Release year
                        <input class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text"
                            type="number" name="year" value="{{ old('year', now()->year) }}" min="1900"
                            max="2100" required>
                    </label>
                    <label class="block text-sm text-ef-muted">Exclude albums heard within
                        <select class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text"
                            name="listened_within">
                            @foreach ($recommendationListeningWindows as $value => $label)
                                <option value="{{ $value }}" @selected($value === '1_year')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <button
                    class="mt-4 rounded-md bg-ef-accent px-4 py-2 font-medium text-ef-accent-ink hover:bg-ef-accent-hover"
                    type="submit">Generate suggestions</button>
            </form>
            <form class="mt-4" method="POST" action="{{ route('catalogue.discography', [], false) }}">
                @csrf
                <input type="hidden" name="artist_id" value="{{ $selectedArtist->id }}">
                <label class="mt-4 flex items-start gap-2 text-sm text-ef-muted">
                    <input class="mt-1" type="checkbox" name="include_non_albums" value="1">
                    <span>Include singles and other non-album releases</span>
                </label>
                <button
                    class="rounded-md border border-ef-accent px-4 py-2 font-medium text-ef-accent-strong hover:border-ef-accent-hover"
                    type="submit">Generate discography draft</button>
            </form>
        </section>
    @endif

    @if ($selectedAlbum)
        <section class="rounded-xl border border-ef-accent-line bg-ef-accent-wash/30 p-6"
            aria-labelledby="selected-album-heading">
            <p class="text-xs tracking-[0.2em] text-ef-accent-strong uppercase">Selected album</p>
            <h2 id="selected-album-heading" class="mt-2 text-2xl font-semibold">{{ $selectedAlbum->displayTitle() }}</h2>
            <p class="mt-2 flex flex-wrap gap-x-2 text-ef-muted">
                @forelse ($selectedAlbum->artists as $artist)
                    @if (!$loop->first)
                        <span aria-hidden="true">·</span>
                    @endif
                    <a class="hover:text-ef-accent-strong"
                        href="{{ route('artists.show', $artist, false) }}">{{ $artist->name }}</a>
                @empty
                    <span>Unknown artist</span>
                @endforelse
            </p>
            <p class="mt-2 text-sm text-ef-muted">{{ $selectedAlbum->tracks->count() }} canonical
                tracks{{ $selectedAlbum->release_date ? ' · ' . $selectedAlbum->release_date : '' }}</p>
            <p class="mt-5 text-sm text-ef-muted">This album is ready to become the context for genre, year, and
                completion-aware discovery.</p>
            <form class="mt-6 border-t border-ef-accent-line pt-6" method="POST"
                action="{{ route('catalogue.discover', [], false) }}">
                @csrf
                <input type="hidden" name="album_id" value="{{ $selectedAlbum->id }}">
                <p class="text-sm font-medium text-ef-text">Find incomplete albums in this style</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm text-ef-muted">Genre
                        <select class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text" name="genre"
                            required>
                            <option value="">Choose a style</option>
                            @foreach ($catalogueGenres as $genre)
                                <option value="{{ $genre->normalised_name }}" @selected(old('genre', $selectedAlbum->artists->flatMap->acceptedGenres->first()?->normalised_name) === $genre->normalised_name)>
                                    {{ $genre->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm text-ef-muted">Release year
                        <input class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text"
                            type="number" name="year"
                            value="{{ old('year', substr((string) $selectedAlbum->release_date, 0, 4) ?: now()->year) }}"
                            min="1900" max="2100" required>
                    </label>
                    <label class="block text-sm text-ef-muted">Exclude albums heard within
                        <select class="mt-2 block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text"
                            name="listened_within">
                            @foreach ($recommendationListeningWindows as $value => $label)
                                <option value="{{ $value }}" @selected($value === '1_year')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <button
                    class="mt-4 rounded-md bg-ef-accent px-4 py-2 font-medium text-ef-accent-ink hover:bg-ef-accent-hover"
                    type="submit">Generate suggestions</button>
            </form>
        </section>
    @endif

    @if ($search !== '')
        <div class="grid gap-6 md:grid-cols-2">
            <section class="rounded-xl border border-ef-line bg-ef-surface p-6"
                aria-labelledby="catalogue-search-artists">
                <h2 id="catalogue-search-artists" class="text-xl font-medium">Artists</h2>
                @if ($artists->isNotEmpty())
                    <ul class="mt-5 space-y-3">
                        @foreach ($artists as $artist)
                            <li>
                                <a class="block rounded-lg border border-ef-line bg-ef-canvas p-4 hover:border-ef-accent"
                                    href="{{ route('artists.show', $artist, false) }}">
                                    <h3 class="font-medium">{{ $artist->name }}</h3>
                                    <p class="mt-1 text-sm text-ef-muted">{{ $artist->albums_count }} canonical albums</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-5 text-sm text-ef-muted">No matching artists.</p>
                @endif
            </section>

            <section class="rounded-xl border border-ef-line bg-ef-surface p-6" aria-labelledby="catalogue-search-albums">
                <h2 id="catalogue-search-albums" class="text-xl font-medium">Albums</h2>
                @if ($albums->isNotEmpty())
                    <ul class="mt-5 space-y-3">
                        @foreach ($albums as $album)
                            <li>
                                <a class="block rounded-lg border border-ef-line bg-ef-canvas p-4 hover:border-ef-accent"
                                    href="{{ route('albums.show', $album, false) }}">
                                    <h3 class="font-medium">{{ $album->displayTitle() }}</h3>
                                    <p class="mt-1 text-sm text-ef-muted">
                                        {{ $album->artists->pluck('name')->implode(', ') ?: 'Unknown artist' }}</p>
                                    <p class="mt-1 text-sm text-ef-muted">{{ $album->tracks_count }} canonical tracks</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-5 text-sm text-ef-muted">No matching albums.</p>
                @endif
            </section>
        </div>
    @elseif (!($filteredAlbums instanceof \Illuminate\Pagination\LengthAwarePaginator))
        <section class="rounded-xl border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="catalogue-artists">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-ef-line pb-5">
                <div>
                    <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Your Catalogue</p>
                    <h2 id="catalogue-artists" class="mt-2 text-2xl font-semibold">Artists</h2>
                </div>
                <span class="text-sm text-ef-muted">{{ number_format($artists->total()) }}
                    {{ $artists->total() === 1 ? 'artist' : 'artists' }}</span>
            </div>
            @if ($artists->isNotEmpty())
                <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($artists as $artist)
                        <li>
                            <a class="block border border-ef-line bg-ef-canvas p-5 transition hover:border-ef-accent"
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
                <p class="mt-5 text-sm text-ef-muted">No artists have been added to the catalogue yet.</p>
            @endif
        </section>
    @endif
@endsection
