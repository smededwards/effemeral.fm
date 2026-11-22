@extends('layouts.app', ['title' => 'Overview · Effemeral'])

@section('content')
    <header class="flex flex-col gap-6 border-b border-ef-line pb-10 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl">
            <p class="text-xs font-semibold tracking-[0.24em] text-ef-accent-strong">Overview / 01</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Your Listening Archive</h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-ef-muted">Last.fm is your listening record. Spotify is the working library. Effemeral brings them together so unfinished albums become deliberate next listens.</p>
        </div>
        <a class="inline-flex w-fit items-center border border-ef-accent bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink transition hover:bg-ef-accent-hover" href="{{ route('recommendations.index', [], false) }}">See Albums to Complete <span class="ml-4" aria-hidden="true">→</span></a>
    </header>

    <section class="grid border-y border-ef-line bg-ef-surface sm:grid-cols-2 lg:grid-cols-4" aria-label="Archive Summary">
        <div class="border-b border-ef-line p-5 sm:border-r lg:border-b-0">
            <p class="text-xs font-semibold tracking-[0.18em] text-ef-muted">Scrobbles stored</p>
            <p class="mt-4 text-4xl font-semibold tabular-nums tracking-[-0.04em]">{{ number_format($scrobbleCount) }}</p>
        </div>
        <div class="border-b border-ef-line p-5 lg:border-r lg:border-b-0">
            <p class="text-xs font-semibold tracking-[0.18em] text-ef-muted">Albums to complete</p>
            <p class="mt-4 text-4xl font-semibold tabular-nums tracking-[-0.04em]">{{ number_format($incompleteAlbums->count()) }}</p>
        </div>
        <div class="border-b border-ef-line p-5 sm:border-r sm:border-b-0">
            <p class="text-xs font-semibold tracking-[0.18em] text-ef-muted">Spotify albums</p>
            <p class="mt-4 text-4xl font-semibold tabular-nums tracking-[-0.04em]">{{ number_format($spotifySavedAlbumCount) }}</p>
        </div>
        <div class="p-5">
            <p class="text-xs font-semibold tracking-[0.18em] text-ef-muted">Latest import</p>
            <p class="mt-4 text-2xl font-semibold">{{ ucfirst(str_replace('_', ' ', $latestImport?->status ?? 'Not started')) }}</p>
            <p class="mt-1 text-sm text-ef-muted">{{ ($latestImport?->completed_at ?? $latestImport?->started_at ?? $latestImport?->created_at)?->format('Y.m.d H:i') ?? 'Import Last.fm to begin' }}</p>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <div class="min-w-0 border border-ef-line bg-ef-surface p-6 sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong">Next listening</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Albums to Complete</h2>
                    <p class="mt-2 text-sm text-ef-muted">Saved albums with tracks not yet heard in Last.fm.</p>
                </div>
                <a class="text-sm font-semibold text-ef-accent-strong hover:underline" href="{{ route('recommendations.index', [], false) }}">Open Drafts →</a>
            </div>
            @if ($incompleteAlbums->isNotEmpty())
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($incompleteAlbums->take(6) as $album)
                        <article class="group overflow-hidden border border-ef-line bg-ef-canvas">
                            @if ($album['cover_image_url'])
                            <img class="aspect-square w-full object-cover transition duration-300 motion-safe:group-hover:scale-[1.02]" src="{{ $album['cover_image_url'] }}" alt="Cover art for {{ $album['name'] }}">
                            @else
                                <div class="flex aspect-square items-center justify-center bg-ef-surface-raised text-xs font-medium tracking-[0.12em] text-ef-muted">No artwork</div>
                            @endif
                            <div class="p-4">
                                <h3 class="truncate font-semibold">{{ $album['name'] }}</h3>
                                <p class="mt-1 truncate text-sm text-ef-muted">{{ $album['artist'] }}</p>
                                <div class="mt-4 flex items-center justify-between text-xs font-medium tabular-nums text-ef-muted"><span>{{ $album['heard'] }} / {{ $album['total'] }} tracks heard</span><span>{{ $album['percentage'] }}%</span></div>
                                <div class="mt-2 h-1 bg-ef-track" role="progressbar" aria-label="{{ $album['name'] }} completion" aria-valuenow="{{ $album['percentage'] }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full bg-ef-accent" style="width: {{ $album['percentage'] }}%"></div></div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="mt-8 border border-dashed border-ef-line p-6 text-sm text-ef-muted">No incomplete albums are available yet.</p>
            @endif
        </div>

        <aside class="border border-ef-line bg-ef-surface-raised p-6 text-ef-text sm:p-8">
            <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong">Actions</p>
            <h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Keep the Archive Current</h2>
            <div class="mt-8 divide-y divide-ef-line border-y border-ef-line">
                <a class="flex items-center justify-between py-4 text-sm font-semibold hover:text-ef-accent-strong" href="{{ route('imports.index', [], false) }}"><span>Import Last.fm and Spotify</span><span aria-hidden="true">→</span></a>
                <a class="flex items-center justify-between py-4 text-sm font-semibold hover:text-ef-accent-strong" href="{{ route('recommendations.index', [], false) }}"><span>Review Albums to Complete</span><span aria-hidden="true">→</span></a>
                <a class="flex items-center justify-between py-4 text-sm font-semibold hover:text-ef-accent-strong" href="{{ route('playlists.index', [], false) }}"><span>Organise Spotify Playlists</span><span aria-hidden="true">→</span></a>
            </div>
            @if ($latestRecommendation)
                <div class="mt-8 border-t border-ef-line pt-5 text-sm text-ef-muted">
                    <p class="font-semibold text-ef-text">Latest recommendation draft</p>
                    <p class="mt-1">{{ $latestRecommendation->albumRecommendations->count() }} albums · {{ $latestRecommendation->status }}</p>
                    <a class="mt-3 inline-block font-semibold text-ef-accent-strong underline" href="{{ route('recommendations.review.page', $latestRecommendation, false) }}">Review {{ $latestRecommendation->albumRecommendations->first()?->spotifySavedAlbum?->name ?? 'Draft' }}</a>
                </div>
            @endif
        </aside>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <div class="min-w-0 border border-ef-line bg-ef-surface p-6 sm:p-8">
            <div class="flex items-end justify-between gap-4 border-b border-ef-line pb-4">
                <div><p class="text-xs font-semibold tracking-[0.18em] text-ef-muted">Last.fm</p><h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Top Artists</h2></div>
                <span class="text-xs text-ef-muted">Most scrobbled</span>
            </div>
            <div class="divide-y divide-ef-surface-raised">
                @forelse ($topArtists as $position => $artist)
                    <div class="flex items-center justify-between gap-4 py-4"><div class="flex min-w-0 items-center gap-4"><span class="text-xs font-semibold tabular-nums text-ef-accent-strong">{{ str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT) }}</span><div class="min-w-0"><h3 class="truncate font-semibold">{{ $artist['name'] }}</h3><p class="mt-1 text-xs text-ef-muted">Last heard {{ $artist['last_listened_at'] }}</p></div></div><span class="whitespace-nowrap text-sm tabular-nums text-ef-muted">{{ number_format($artist['scrobbles']) }} plays</span></div>
                @empty
                    <p class="py-6 text-sm text-ef-muted">No Last.fm scrobbles have been imported yet.</p>
                @endforelse
            </div>
        </div>
        <div class="min-w-0 border border-ef-line bg-ef-surface p-6 sm:p-8">
            <div class="flex items-end justify-between gap-4 border-b border-ef-line pb-4">
                <div><p class="text-xs font-semibold tracking-[0.18em] text-ef-muted">Last.fm</p><h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Top Albums</h2></div>
                <span class="text-xs text-ef-muted">Most played</span>
            </div>
            <div class="divide-y divide-ef-surface-raised">
                @forelse ($topAlbums as $position => $album)
                    <div class="flex items-center justify-between gap-4 py-4"><div class="flex min-w-0 items-center gap-4"><span class="text-xs font-semibold tabular-nums text-ef-accent-strong">{{ str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT) }}</span><div class="min-w-0"><h3 class="truncate font-semibold">{{ $album['name'] }}</h3><p class="mt-1 truncate text-sm text-ef-muted">{{ $album['artist'] }}</p><p class="mt-1 text-xs text-ef-muted">Last heard {{ $album['last_listened_at'] }}</p></div></div><span class="whitespace-nowrap text-sm tabular-nums text-ef-muted">{{ number_format($album['scrobbles']) }} plays</span></div>
                @empty
                    <p class="py-6 text-sm text-ef-muted">No albums are available from the imported scrobbles yet.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="border border-ef-line bg-ef-surface p-6 sm:p-8">
        <div class="flex flex-col gap-4 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-xs font-semibold tracking-[0.18em] text-ef-muted">Spotify library</p><h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Imported Spotify Albums</h2><p class="mt-2 text-sm text-ef-muted">Search the working library or open it for the complete album view.</p></div>
            <a class="text-sm font-semibold text-ef-accent-strong hover:underline" href="{{ route('library.index', [], false) }}">Open Library →</a>
        </div>
        @if ($spotifySavedAlbumCount > 0)
            <form class="mt-6 flex flex-col gap-3 sm:flex-row" method="GET" action="{{ route('dashboard', [], false) }}">
                <label class="sr-only" for="overview-album-search">Search imported albums</label>
                <input id="overview-album-search" class="min-w-0 flex-1 border border-ef-line bg-ef-canvas px-4 py-3 text-sm placeholder:text-ef-muted focus:border-ef-accent focus:outline-none focus:ring-2 focus:ring-ef-accent-soft" type="search" name="album_search" value="{{ $albumSearch }}" placeholder="Search album or artist">
                <button class="border border-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-strong hover:bg-ef-accent-hover hover:text-ef-accent-ink" type="submit">Search</button>
            </form>
        @endif
        @if ($spotifySavedAlbums->isNotEmpty())
            <div class="mt-6 grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($spotifySavedAlbums as $album)
                    <article class="min-w-0 border border-ef-line bg-ef-canvas p-4">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="truncate font-semibold">{{ $album->name }}</h3><p class="mt-1 truncate text-sm text-ef-muted">{{ $album->primary_artist_name ?? 'Unknown artist' }}</p></div>@if ($album->spotify_url)<a class="shrink-0 text-xs font-semibold text-ef-accent-strong hover:underline" href="{{ $album->spotify_url }}" target="_blank" rel="noreferrer">Spotify</a>@endif</div>
                        <p class="mt-4 text-xs tabular-nums text-ef-muted">{{ $album->total_tracks ?? 0 }} tracks · {{ $album->release_date ?: 'Release date unknown' }}</p>
                    </article>
                @endforeach
            </div>
        @else
            <p class="mt-6 border border-dashed border-ef-line p-6 text-sm text-ef-muted">No saved Spotify albums have been imported yet.</p>
        @endif
    </section>
@endsection
