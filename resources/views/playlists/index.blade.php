@extends('layouts.app', ['title' => 'Spotify Playlists · Effemeral'])

@section('content')
    <header class="flex flex-col gap-5 border-b border-ef-line pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold tracking-[0.24em] text-ef-accent-strong uppercase">Playlists</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Spotify Playlist Organisation</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-[#9aa39d]">Browse imported playlists, audit album coverage, and choose a safe destination for approved album drafts.</p>
        </div>
        <form method="POST" action="{{ route('imports.spotify.playlists', [], false) }}">
            @csrf
            <button class="bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover" type="submit">Import all playlists</button>
        </form>
    </header>

    <section class="border border-ef-accent-line bg-ef-surface p-6 sm:p-8" aria-labelledby="effemeral-playlists">
        <div class="flex flex-col gap-4 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Your playlists</p>
                <h2 id="effemeral-playlists" class="mt-2 text-2xl font-semibold">Effemeral Album Playlists</h2>
                <p class="mt-2 text-sm text-ef-muted">Build persistent album lists. Changes here stay in Effemeral until you choose to sync later.</p>
            </div>
            <form class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row" method="POST" action="{{ route('album-playlists.store', [], false) }}">
                @csrf
                <label class="sr-only" for="new-album-playlist-name">New playlist name</label>
                <input id="new-album-playlist-name" class="min-w-0 border border-ef-line bg-ef-canvas px-4 py-3 text-sm text-ef-text" type="text" name="name" maxlength="100" placeholder="e.g. New albums" required>
                <button class="bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover" type="submit">Create Playlist</button>
            </form>
        </div>
        @if ($albumPlaylists->isNotEmpty())
            <ul class="mt-4 divide-y divide-ef-line">
                @foreach ($albumPlaylists as $albumPlaylist)
                    <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold">{{ $albumPlaylist->name }}</h3>
                            <p class="mt-1 text-sm text-ef-muted">{{ $albumPlaylist->albums_count }} {{ $albumPlaylist->albums_count === 1 ? 'album' : 'albums' }} · {{ $albumPlaylist->spotify_playlist_id ? 'Spotify linked' : 'Draft' }}</p>
                        </div>
                        <a class="font-semibold text-ef-accent-strong hover:underline" href="{{ route('album-playlists.show', $albumPlaylist, false) }}">Manage Playlist →</a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-5 text-sm text-ef-muted">No Effemeral playlists yet. Create one to start arranging full albums.</p>
        @endif
    </section>

    @if ($latestPlaylistImport)
        <section class="border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="playlist-import-status">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Latest import</p>
                    <h2 id="playlist-import-status" class="mt-2 text-2xl font-semibold">{{ ucfirst(str_replace('_', ' ', $latestPlaylistImport->status)) }}</h2>
                    <p class="mt-2 text-sm text-ef-muted">{{ number_format($latestPlaylistImport->imported_count) }} new · {{ number_format($latestPlaylistImport->updated_count) }} updated · {{ number_format($latestPlaylistImport->fetched_count) }} tracks</p>
                </div>
                @if ($latestPlaylistImport->completed_at)
                <time class="text-xs tabular-nums text-ef-muted" datetime="{{ $latestPlaylistImport->completed_at->toIso8601String() }}">{{ $latestPlaylistImport->completed_at->format('Y.m.d H:i') }}</time>
                @endif
            </div>
            @if ($latestPlaylistImport->error)
                <div class="mt-5 border-l-4 border-ef-accent-strong bg-ef-canvas px-4 py-3 text-sm text-ef-accent-soft" role="status">
                    {{ count(explode("\n", $latestPlaylistImport->error)) }} playlist import(s) need attention. Review the list below to retry them.
                </div>
            @endif
        </section>
    @endif

    @if ($failedPlaylists->isNotEmpty())
        <section class="border border-ef-accent-line bg-ef-surface p-6 sm:p-8" aria-labelledby="playlist-failures">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Import warnings</p>
                    <h2 id="playlist-failures" class="mt-2 text-2xl font-semibold">Playlists Needing a Retry</h2>
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
                            <button class="shrink-0 border border-ef-accent px-4 py-3 text-sm font-semibold text-ef-accent-strong hover:bg-ef-accent-wash" type="submit">Retry playlist</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="border border-ef-line bg-ef-canvas p-6 sm:p-8" aria-labelledby="playlist-list">
        <div class="flex flex-col gap-2 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Imported Spotify data</p>
                <h2 id="playlist-list" class="mt-2 text-2xl font-semibold">All Playlists</h2>
            </div>
            <span class="text-sm text-ef-muted">{{ number_format($playlists->total()) }} playlists</span>
        </div>
        <form class="mt-6 flex flex-col gap-3 sm:flex-row" method="GET" action="{{ route('playlists.index', [], false) }}">
            <label class="sr-only" for="playlist-search">Search playlists</label>
            <input id="playlist-search" class="min-w-0 flex-1 border border-ef-line bg-ef-surface px-4 py-3 text-sm text-ef-text placeholder:text-ef-muted focus:border-ef-accent focus:outline-none focus:ring-2 focus:ring-ef-accent-strong" type="search" name="playlist_search" value="{{ $playlistSearch }}" placeholder="Search imported playlists">
            <button class="bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover" type="submit">Search</button>
            @if ($playlistSearch !== '')
                <a class="border border-ef-line px-5 py-3 text-center text-sm font-semibold text-ef-accent-soft hover:border-ef-accent" href="{{ route('playlists.index', [], false) }}">Clear</a>
            @endif
        </form>
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
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-ef-muted">
                            <span>{{ number_format($playlist->items_count) }} imported tracks · {{ number_format($playlist->tracks_total) }} Spotify tracks</span>
                            <span class="border px-2 py-1 text-xs {{ $playlist->import_status === 'failed' ? 'border-red-800 text-red-300' : ($playlist->import_status === 'partial' ? 'border-ef-accent-strong text-ef-accent-strong' : 'border-ef-line text-ef-muted') }}">{{ ucfirst($playlist->import_status) }}</span>
                            <span class="border px-2 py-1 text-xs {{ $playlist->album_status === 'complete' ? 'border-ef-accent text-ef-accent-strong' : ($playlist->album_status === 'partial' ? 'border-ef-accent-strong text-ef-accent-strong' : 'border-ef-line text-ef-muted') }}">{{ $playlist->album_status === 'none' ? 'No album detected' : ($playlist->complete_album_count.' complete album'.($playlist->complete_album_count === 1 ? '' : 's')) }}</span>
                        </div>
                        <p class="mt-1 text-xs text-ef-muted">Last synced {{ $playlist->last_synced_at?->format('Y.m.d H:i') ?? 'not yet' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <a class="font-semibold text-ef-accent-strong hover:underline" href="{{ route('recommendations.audit', ['playlist' => $playlist->id], false) }}">Audit Coverage</a>
                        @if ($playlist->spotify_url)
                            <a class="font-semibold text-ef-accent-strong hover:underline" href="{{ $playlist->spotify_url }}" target="_blank" rel="noreferrer">Open Spotify</a>
                        @endif
                    </div>
                </article>
            @empty
                <p class="py-6 text-sm text-ef-muted">No Spotify playlists have been imported yet.</p>
            @endforelse
        </div>
        @if ($playlists->hasPages())
            <div class="mt-6 border-t border-ef-line pt-5">{{ $playlists->links() }}</div>
        @endif
    </section>
@endsection
