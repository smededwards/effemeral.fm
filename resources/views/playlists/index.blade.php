@extends('layouts.app', ['title' => 'Spotify playlists · Effemeral'])

@section('content')
    <header class="flex flex-col gap-5 border-b border-ef-line pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-ef-accent-strong">Playlists</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Spotify playlist organisation</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-ef-muted">Browse imported playlists, audit album coverage, and choose a safe destination for approved album drafts.</p>
        </div>
        <form method="POST" action="{{ route('imports.spotify.playlists', [], false) }}">
            @csrf
            <button class="bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-soft" type="submit">Import all playlists</button>
        </form>
    </header>

    @if ($latestPlaylistImport)
        <section class="border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="playlist-import-status">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ef-accent-strong">Latest import</p>
                    <h2 id="playlist-import-status" class="mt-2 text-2xl font-semibold capitalize">{{ str_replace('_', ' ', $latestPlaylistImport->status) }}</h2>
                    <p class="mt-2 text-sm text-ef-muted">{{ number_format($latestPlaylistImport->imported_count) }} new · {{ number_format($latestPlaylistImport->updated_count) }} updated · {{ number_format($latestPlaylistImport->fetched_count) }} tracks</p>
                </div>
                @if ($latestPlaylistImport->completed_at)
                    <time class="text-xs tabular-nums text-ef-muted" datetime="{{ $latestPlaylistImport->completed_at->toIso8601String() }}">{{ $latestPlaylistImport->completed_at->format('Y.m.d H:i') }}</time>
                @endif
            </div>
            @if ($latestPlaylistImport->error)
                <div class="mt-5 border-l-4 border-ef-accent bg-ef-canvas px-4 py-3 text-sm text-ef-accent-soft" role="status">
                    {{ count(explode("\n", $latestPlaylistImport->error)) }} playlist import(s) need attention. Review the list below to retry them.
                </div>
            @endif
        </section>
    @endif

    @if ($failedPlaylists->isNotEmpty())
        <section class="border border-amber-900 bg-ef-surface p-6 sm:p-8" aria-labelledby="playlist-failures">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ef-accent-strong">Import warnings</p>
                    <h2 id="playlist-failures" class="mt-2 text-2xl font-semibold">Playlists needing a retry</h2>
                </div>
                <span class="text-sm text-ef-muted">{{ $failedPlaylists->count() }} recorded</span>
            </div>
            <div class="mt-6 divide-y divide-ef-line">
                @foreach ($failedPlaylists as $playlist)
                    <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="truncate font-semibold">{{ $playlist->name }}</h3>
                            <p class="mt-1 truncate text-sm text-ef-muted">{{ $playlist->last_import_error }}</p>
                        </div>
                        <form method="POST" action="{{ route('imports.spotify.playlists.retry', $playlist, false) }}">
                            @csrf
                            <button class="shrink-0 border border-ef-accent px-4 py-2 text-sm font-semibold text-ef-accent-strong hover:bg-ef-accent-wash" type="submit">Retry playlist</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="border border-ef-line bg-ef-canvas p-6 sm:p-8" aria-labelledby="playlist-list">
        <div class="flex flex-col gap-2 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-ef-accent-strong">Imported Spotify data</p>
                <h2 id="playlist-list" class="mt-2 text-2xl font-semibold">All playlists</h2>
            </div>
            <span class="text-sm text-ef-muted">{{ number_format($playlists->count()) }} playlists</span>
        </div>
        <div class="mt-6 divide-y divide-ef-line">
            @forelse ($playlists as $playlist)
                <article class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="truncate font-semibold">{{ $playlist->name }}</h3>
                            @if ($playlist->public === true)
                                <span class="border border-ef-line px-2 py-1 text-xs text-ef-muted">Public</span>
                            @endif
                            @if ($playlist->collaborative)
                                <span class="border border-ef-line px-2 py-1 text-xs text-ef-muted">Collaborative</span>
                            @endif
                        </div>
                        <p class="mt-2 text-sm text-ef-muted">{{ number_format($playlist->items_count) }} imported tracks · {{ number_format($playlist->tracks_total) }} Spotify tracks</p>
                        <p class="mt-1 text-xs text-ef-muted">Last synced {{ $playlist->last_synced_at?->format('Y.m.d H:i') ?? 'not yet' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <a class="font-semibold text-ef-accent-strong hover:underline" href="{{ route('recommendations.audit', ['playlist' => $playlist->id], false) }}">Audit coverage</a>
                        @if ($playlist->spotify_url)
                            <a class="font-semibold text-ef-accent-strong hover:underline" href="{{ $playlist->spotify_url }}" target="_blank" rel="noreferrer">Open Spotify</a>
                        @endif
                    </div>
                </article>
            @empty
                <p class="py-6 text-sm text-ef-muted">No Spotify playlists have been imported yet.</p>
            @endforelse
        </div>
    </section>
@endsection
