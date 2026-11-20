@extends('layouts.app', ['title' => 'Overview · Effemeral'])

@section('content')
    <header class="flex flex-col gap-6 border-b border-[#2e382f] pb-10 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#00e676]">Overview / 01</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Your listening archive</h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-[#9aa39d]">Last.fm is your listening record. Spotify is the working library. Effemeral brings them together so unfinished albums become deliberate next listens.</p>
        </div>
        <a class="inline-flex w-fit items-center border border-[#00e676] bg-[#00e676] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#00994d]" href="{{ route('catalogue.index') }}">Browse catalogue <span class="ml-4" aria-hidden="true">→</span></a>
    </header>

    <section class="grid border-y border-[#2e382f] bg-[#111612] sm:grid-cols-2 lg:grid-cols-4" aria-label="Archive summary">
        <div class="border-b border-[#2e382f] p-5 sm:border-r lg:border-b-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#9aa39d]">Scrobbles stored</p>
            <p class="mt-4 text-4xl font-semibold tabular-nums tracking-[-0.04em]">{{ number_format($scrobbleCount) }}</p>
        </div>
        <div class="border-b border-[#2e382f] p-5 lg:border-r lg:border-b-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#9aa39d]">Albums to complete</p>
            <p class="mt-4 text-4xl font-semibold tabular-nums tracking-[-0.04em]">{{ number_format($incompleteAlbums->count()) }}</p>
        </div>
        <div class="border-b border-[#2e382f] p-5 sm:border-r sm:border-b-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#9aa39d]">Spotify albums</p>
            <p class="mt-4 text-4xl font-semibold tabular-nums tracking-[-0.04em]">{{ number_format($spotifySavedAlbumCount) }}</p>
        </div>
        <div class="p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#9aa39d]">Latest import</p>
            <p class="mt-4 text-2xl font-semibold capitalize">{{ $latestImport?->status ?? 'Not started' }}</p>
            <p class="mt-1 text-sm text-[#9aa39d]">{{ $latestImport?->created_at?->format('Y.m.d H:i') ?? 'Import Last.fm to begin' }}</p>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <div class="min-w-0 border border-[#2e382f] bg-[#111612] p-6 sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#00e676]">Next listening</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Albums to complete</h2>
                    <p class="mt-2 text-sm text-[#9aa39d]">Saved albums with tracks not yet heard in Last.fm.</p>
                </div>
                <a class="text-sm font-semibold text-[#00e676] hover:underline" href="{{ route('recommendations.index') }}">Open drafts →</a>
            </div>
            @if ($incompleteAlbums->isNotEmpty())
                <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($incompleteAlbums->take(6) as $album)
                        <article class="group overflow-hidden border border-[#2e382f] bg-[#0b0c0a]">
                            @if ($album['cover_image_url'])
                                <img class="aspect-square w-full object-cover transition duration-300 group-hover:scale-[1.02]" src="{{ $album['cover_image_url'] }}" alt="Cover art for {{ $album['name'] }}">
                            @else
                                <div class="flex aspect-square items-center justify-center bg-[#1a211c] text-xs font-medium uppercase tracking-[0.12em] text-[#9aa39d]">No artwork</div>
                            @endif
                            <div class="p-4">
                                <h3 class="truncate font-semibold">{{ $album['name'] }}</h3>
                                <p class="mt-1 truncate text-sm text-[#9aa39d]">{{ $album['artist'] }}</p>
                                <div class="mt-4 flex items-center justify-between text-xs font-medium tabular-nums text-[#9aa39d]"><span>{{ $album['heard'] }} / {{ $album['total'] }} tracks heard</span><span>{{ $album['percentage'] }}%</span></div>
                                <div class="mt-2 h-1 bg-[#334038]" role="progressbar" aria-label="{{ $album['name'] }} completion" aria-valuenow="{{ $album['percentage'] }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full bg-[#00e676]" style="width: {{ $album['percentage'] }}%"></div></div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="mt-8 border border-dashed border-[#2e382f] p-6 text-sm text-[#9aa39d]">No incomplete albums are available yet.</p>
            @endif
        </div>

        <aside class="border border-[#2e382f] bg-[#00e676] p-6 text-white sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#b7ffd1]">Actions</p>
            <h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Keep the archive current</h2>
            <div class="mt-8 divide-y divide-white/20 border-y border-white/20">
                <a class="flex items-center justify-between py-4 text-sm font-semibold hover:text-[#b7ffd1]" href="{{ route('imports.index') }}"><span>Import Last.fm and Spotify</span><span aria-hidden="true">→</span></a>
                <a class="flex items-center justify-between py-4 text-sm font-semibold hover:text-[#b7ffd1]" href="{{ route('library.index') }}"><span>Review your library</span><span aria-hidden="true">→</span></a>
                <a class="flex items-center justify-between py-4 text-sm font-semibold hover:text-[#b7ffd1]" href="{{ route('recommendations.index') }}"><span>Review playlist drafts</span><span aria-hidden="true">→</span></a>
            </div>
            @if ($latestRecommendation)
                <div class="mt-8 border-t border-white/20 pt-5 text-sm text-[#d7ffe2]">
                    <p class="font-semibold text-white">Latest recommendation draft</p>
                    <p class="mt-1">{{ $latestRecommendation->albumRecommendations->count() }} albums · {{ $latestRecommendation->status }}</p>
                    <a class="mt-3 inline-block font-semibold text-white underline" href="{{ route('recommendations.review.page', $latestRecommendation) }}">Review {{ $latestRecommendation->albumRecommendations->first()?->spotifySavedAlbum?->name ?? 'draft' }}</a>
                </div>
            @endif
        </aside>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <div class="min-w-0 border border-[#2e382f] bg-[#111612] p-6 sm:p-8">
            <div class="flex items-end justify-between gap-4 border-b border-[#2e382f] pb-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#9aa39d]">Last.fm</p><h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Top artists</h2></div>
                <span class="text-xs text-[#9aa39d]">Most scrobbled</span>
            </div>
            <div class="divide-y divide-[#1a211c]">
                @forelse ($topArtists as $position => $artist)
                    <div class="flex items-center justify-between gap-4 py-4"><div class="flex min-w-0 items-center gap-4"><span class="text-xs font-semibold tabular-nums text-[#00e676]">{{ str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT) }}</span><div class="min-w-0"><h3 class="truncate font-semibold">{{ $artist['name'] }}</h3><p class="mt-1 text-xs text-[#9aa39d]">Last heard {{ $artist['last_listened_at'] }}</p></div></div><span class="whitespace-nowrap text-sm tabular-nums text-[#9aa39d]">{{ number_format($artist['scrobbles']) }} plays</span></div>
                @empty
                    <p class="py-6 text-sm text-[#9aa39d]">No Last.fm scrobbles have been imported yet.</p>
                @endforelse
            </div>
        </div>
        <div class="min-w-0 border border-[#2e382f] bg-[#111612] p-6 sm:p-8">
            <div class="flex items-end justify-between gap-4 border-b border-[#2e382f] pb-4">
                <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#9aa39d]">Last.fm</p><h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Top albums</h2></div>
                <span class="text-xs text-[#9aa39d]">Most played</span>
            </div>
            <div class="divide-y divide-[#1a211c]">
                @forelse ($topAlbums as $position => $album)
                    <div class="flex items-center justify-between gap-4 py-4"><div class="flex min-w-0 items-center gap-4"><span class="text-xs font-semibold tabular-nums text-[#00e676]">{{ str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT) }}</span><div class="min-w-0"><h3 class="truncate font-semibold">{{ $album['name'] }}</h3><p class="mt-1 truncate text-sm text-[#9aa39d]">{{ $album['artist'] }}</p><p class="mt-1 text-xs text-[#9aa39d]">Last heard {{ $album['last_listened_at'] }}</p></div></div><span class="whitespace-nowrap text-sm tabular-nums text-[#9aa39d]">{{ number_format($album['scrobbles']) }} plays</span></div>
                @empty
                    <p class="py-6 text-sm text-[#9aa39d]">No albums are available from the imported scrobbles yet.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="border border-[#2e382f] bg-[#111612] p-6 sm:p-8">
        <div class="flex flex-col gap-4 border-b border-[#2e382f] pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#9aa39d]">Spotify library</p><h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Imported Spotify albums</h2><p class="mt-2 text-sm text-[#9aa39d]">Search the working library or open it for the complete album view.</p></div>
            <a class="text-sm font-semibold text-[#00e676] hover:underline" href="{{ route('library.index') }}">Open library →</a>
        </div>
        @if ($spotifySavedAlbumCount > 0)
            <form class="mt-6 flex flex-col gap-3 sm:flex-row" method="GET" action="{{ route('dashboard') }}">
                <label class="sr-only" for="overview-album-search">Search imported albums</label>
                <input id="overview-album-search" class="min-w-0 flex-1 border border-[#9b9ca4] bg-[#0b0c0a] px-4 py-3 text-sm placeholder:text-[#7b7c83] focus:border-[#00e676] focus:outline-none focus:ring-2 focus:ring-[#b7ffd1]" type="search" name="album_search" value="{{ $albumSearch }}" placeholder="Search album or artist">
                <button class="border border-[#00e676] px-5 py-3 text-sm font-semibold text-[#00e676] hover:bg-[#00e676] hover:text-white" type="submit">Search</button>
            </form>
        @endif
        @if ($spotifySavedAlbums->isNotEmpty())
            <div class="mt-6 grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($spotifySavedAlbums as $album)
                    <article class="min-w-0 border border-[#2e382f] bg-[#0b0c0a] p-4">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="truncate font-semibold">{{ $album->name }}</h3><p class="mt-1 truncate text-sm text-[#9aa39d]">{{ $album->primary_artist_name ?? 'Unknown artist' }}</p></div>@if ($album->spotify_url)<a class="shrink-0 text-xs font-semibold text-[#00e676] hover:underline" href="{{ $album->spotify_url }}" target="_blank" rel="noreferrer">Spotify</a>@endif</div>
                        <p class="mt-4 text-xs tabular-nums text-[#9aa39d]">{{ $album->total_tracks ?? 0 }} tracks · {{ $album->release_date ?: 'Release date unknown' }}</p>
                    </article>
                @endforeach
            </div>
        @else
            <p class="mt-6 border border-dashed border-[#2e382f] p-6 text-sm text-[#9aa39d]">No saved Spotify albums have been imported yet.</p>
        @endif
    </section>
@endsection
