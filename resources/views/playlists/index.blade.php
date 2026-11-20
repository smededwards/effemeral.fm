@extends('layouts.app', ['title' => 'Spotify playlists · Effemeral'])

@section('content')
    <header class="flex flex-col gap-5 border-b border-[#2e382f] pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#00e676]">Playlists</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Spotify playlist organisation</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-[#9aa39d]">Browse imported playlists, audit album coverage, and choose a safe destination for approved album drafts.</p>
        </div>
        <form method="POST" action="{{ route('imports.spotify.playlists', [], false) }}">
            @csrf
            <button class="bg-[#00e676] px-5 py-3 text-sm font-semibold text-[#061108] hover:bg-[#b7ffd1]" type="submit">Import all playlists</button>
        </form>
    </header>

    @if ($latestPlaylistImport)
        <section class="border border-[#2e382f] bg-[#111612] p-6 sm:p-8" aria-labelledby="playlist-import-status">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#00e676]">Latest import</p>
                    <h2 id="playlist-import-status" class="mt-2 text-2xl font-semibold capitalize">{{ str_replace('_', ' ', $latestPlaylistImport->status) }}</h2>
                    <p class="mt-2 text-sm text-[#9aa39d]">{{ number_format($latestPlaylistImport->imported_count) }} new · {{ number_format($latestPlaylistImport->updated_count) }} updated · {{ number_format($latestPlaylistImport->fetched_count) }} tracks</p>
                </div>
                @if ($latestPlaylistImport->completed_at)
                    <time class="text-xs tabular-nums text-[#9aa39d]" datetime="{{ $latestPlaylistImport->completed_at->toIso8601String() }}">{{ $latestPlaylistImport->completed_at->format('Y.m.d H:i') }}</time>
                @endif
            </div>
            @if ($latestPlaylistImport->error)
                <div class="mt-5 border-l-4 border-[#c6ff4a] bg-[#0b0c0a] px-4 py-3 text-sm text-[#d7ffe2]" role="status">
                    {{ count(explode("\n", $latestPlaylistImport->error)) }} playlist import(s) need attention. Review the list below to retry them.
                </div>
            @endif
        </section>
    @endif

    @if ($failedPlaylists->isNotEmpty())
        <section class="border border-[#6d3d18] bg-[#17130e] p-6 sm:p-8" aria-labelledby="playlist-failures">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#c6ff4a]">Import warnings</p>
                    <h2 id="playlist-failures" class="mt-2 text-2xl font-semibold">Playlists needing a retry</h2>
                </div>
                <span class="text-sm text-[#9aa39d]">{{ $failedPlaylists->count() }} recorded</span>
            </div>
            <div class="mt-6 divide-y divide-[#3d2d1a]">
                @foreach ($failedPlaylists as $playlist)
                    <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="truncate font-semibold">{{ $playlist->name }}</h3>
                            <p class="mt-1 truncate text-sm text-[#c9b99c]">{{ $playlist->last_import_error }}</p>
                        </div>
                        <form method="POST" action="{{ route('imports.spotify.playlists.retry', $playlist, false) }}">
                            @csrf
                            <button class="shrink-0 border border-[#c6ff4a] px-4 py-2 text-sm font-semibold text-[#c6ff4a] hover:bg-[#272313]" type="submit">Retry playlist</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="border border-[#2e382f] bg-[#0b0c0a] p-6 sm:p-8" aria-labelledby="playlist-list">
        <div class="flex flex-col gap-2 border-b border-[#2e382f] pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#00e676]">Imported Spotify data</p>
                <h2 id="playlist-list" class="mt-2 text-2xl font-semibold">All playlists</h2>
            </div>
            <span class="text-sm text-[#9aa39d]">{{ number_format($playlists->count()) }} playlists</span>
        </div>
        <div class="mt-6 divide-y divide-[#2e382f]">
            @forelse ($playlists as $playlist)
                <article class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="truncate font-semibold">{{ $playlist->name }}</h3>
                            @if ($playlist->public === true)
                                <span class="border border-[#445148] px-2 py-1 text-xs text-[#9aa39d]">Public</span>
                            @endif
                            @if ($playlist->collaborative)
                                <span class="border border-[#445148] px-2 py-1 text-xs text-[#9aa39d]">Collaborative</span>
                            @endif
                        </div>
                        <p class="mt-2 text-sm text-[#9aa39d]">{{ number_format($playlist->items_count) }} imported tracks · {{ number_format($playlist->tracks_total) }} Spotify tracks</p>
                        <p class="mt-1 text-xs text-[#7d8a80]">Last synced {{ $playlist->last_synced_at?->format('Y.m.d H:i') ?? 'not yet' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <a class="font-semibold text-[#00e676] hover:underline" href="{{ route('recommendations.audit', ['playlist' => $playlist->id], false) }}">Audit coverage</a>
                        @if ($playlist->spotify_url)
                            <a class="font-semibold text-[#00e676] hover:underline" href="{{ $playlist->spotify_url }}" target="_blank" rel="noreferrer">Open Spotify</a>
                        @endif
                    </div>
                </article>
            @empty
                <p class="py-6 text-sm text-[#9aa39d]">No Spotify playlists have been imported yet.</p>
            @endforelse
        </div>
    </section>
@endsection
