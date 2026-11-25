@extends('layouts.app', ['title' => $artist->name . ' · Artist · Effemeral'])

@section('content')
    <header class="border-b border-ef-line pb-8">
        <a class="text-sm font-semibold text-ef-accent-strong hover:underline"
            href="{{ route('catalogue.index', [], false) }}">← Catalogue</a>
        <p class="mt-5 text-xs font-semibold tracking-[0.2em] text-ef-accent-strong uppercase">Artist</p>
        <h1 class="mt-2 font-serif text-4xl font-semibold sm:text-5xl">{{ $artist->name }}</h1>
        <p class="mt-3 text-sm text-ef-muted">
            {{ config('countries.' . $artist->country_code, 'Country not set') }}{{ $artist->location ? ' · ' . $artist->location : '' }}
        </p>
        @php($hasLocation = filled($artist->country_code) || filled($artist->location))
        <details class="mt-4 max-w-xl" @unless ($hasLocation) open @endunless>
            @if ($hasLocation)
                <summary class="cursor-pointer text-sm font-semibold text-ef-accent-strong">Edit location</summary>
            @endif
            <form class="mt-4 flex flex-col gap-3 sm:flex-row" method="POST"
                action="{{ route('artists.location.update', $artist, false) }}">
                @csrf
                @method('PATCH')
                <label class="sr-only" for="artist-country">Country</label>
                <select id="artist-country" class="block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text"
                    name="country_code">
                    <option value="">Country not set</option>
                    @foreach (config('countries') as $countryCode => $countryName)
                        <option value="{{ $countryCode }}" @selected($artist->country_code === $countryCode)>{{ $countryName }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="artist-location">City or locality</label>
                <input id="artist-location" class="block w-full rounded-md border-ef-line bg-ef-canvas text-ef-text"
                    type="text" name="location" maxlength="150" value="{{ $artist->location }}"
                    placeholder="City or locality (optional)">
                <button
                    class="shrink-0 rounded-md border border-ef-accent px-4 py-2 text-sm font-semibold text-ef-accent-strong hover:bg-ef-accent-wash"
                    type="submit">Save location</button>
            </form>
        </details>
        @if ($artist->spotify_id)
            <a class="mt-4 inline-block text-sm font-semibold text-ef-accent-strong hover:underline"
                href="https://open.spotify.com/artist/{{ $artist->spotify_id }}" target="_blank" rel="noreferrer">Open in
                Spotify</a>
        @endif
    </header>

    <section class="border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="artist-musicbrainz-discography">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="artist-musicbrainz-discography" class="text-xl font-semibold">MusicBrainz album metadata</h2>
                <p class="mt-2 max-w-2xl text-sm text-ef-muted">Enrich existing albums with MusicBrainz release metadata
                    without
                    importing new albums or tracks.</p>
            </div>
            @if ($artist->musicbrainz_mbid)
                <form method="POST" action="{{ route('artists.musicbrainz-discography.store', $artist, false) }}">
                    @csrf
                    <button
                        class="rounded-md border border-ef-accent px-4 py-2 text-sm font-semibold text-ef-accent-strong hover:bg-ef-accent-wash"
                        type="submit">Refresh album metadata</button>
                </form>
            @else
                <p class="text-sm text-ef-muted">A MusicBrainz artist ID is needed before existing albums can be enriched.
                </p>
            @endif
        </div>
        @if ($artist->musicBrainzDiscographyImportRuns->isNotEmpty())
            @php($discographyRun = $artist->musicBrainzDiscographyImportRuns->first())
            <div class="mt-5 border-t border-ef-line pt-4 text-sm" aria-live="polite">
                <p class="font-medium">Latest refresh: {{ ucfirst($discographyRun->status) }}</p>
                @if ($discographyRun->status === 'completed')
                    <p class="mt-1 text-ef-muted">{{ $discographyRun->fetched_count }} release groups checked ·
                        {{ $discographyRun->matched_count }} existing albums matched ·
                        {{ $discographyRun->unmatched_count }} not in the catalogue</p>
                @elseif ($discographyRun->error)
                    <p class="mt-1 text-red-300">{{ $discographyRun->error }}</p>
                @endif
            </div>
        @endif
    </section>

    <section class="artist-genres-section border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="artist-genres">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="artist-genres" class="text-2xl font-semibold">Genres</h2>
            <details class="artist-genres-editor">
                <summary class="cursor-pointer text-sm font-semibold text-ef-accent-strong">Edit genres</summary>
            </details>
        </div>
        @if ($artist->acceptedGenres->isNotEmpty())
            <ul class="artist-genres-list mt-4 flex flex-wrap gap-2">
                @foreach ($artist->acceptedGenres->sortBy('name') as $genre)
                    <li class="inline-flex items-center border border-ef-accent-line bg-ef-canvas">
                        <a class="inline-block px-3 py-2 text-sm text-ef-accent-soft hover:text-ef-text"
                            href="{{ route('genres.show', $genre, false) }}">{{ $genre->name }}</a>
                        <form class="genre-remove" method="POST"
                            action="{{ route('artists.genres.destroy', [$artist, $genre], false) }}">
                            @csrf
                            @method('DELETE')
                            <button class="px-2 text-ef-muted hover:text-red-300" type="submit"
                                aria-label="Remove {{ $genre->name }}" title="Remove {{ $genre->name }}">×</button>
                        </form>
                    </li>
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
            <span class="text-sm text-ef-muted">{{ $artist->albums->count() }}
                {{ $artist->albums->count() === 1 ? 'album' : 'albums' }}</span>
        </div>
        @if ($artist->albums->isNotEmpty())
            <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($artist->albums as $album)
                    <li>
                        <a class="block border border-ef-line bg-ef-surface p-5 transition hover:border-ef-accent"
                            href="{{ route('albums.show', $album, false) }}">
                            <h3 class="font-semibold">{{ $album->displayTitle() }}</h3>
                            <p class="mt-2 text-sm text-ef-muted">{{ $album->release_date ?: 'Release date unknown' }} ·
                                {{ $album->tracks_count }}
                                tracks{{ $album->spotify_id ? ($album->tracks_count === 0 ? ' · Spotify tracks unavailable' : '') : ' · No Spotify match' }}
                            </p>
                            <span class="mt-4 inline-block text-sm font-semibold text-ef-accent-strong">View Album →</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-5 text-sm text-ef-muted">No canonical albums are linked to this artist yet.</p>
        @endif
    </section>
@endsection
