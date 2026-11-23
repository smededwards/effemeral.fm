@extends('layouts.app', ['title' => 'Overview · Effemeral'])

@section('content')
    <header class="flex flex-col gap-6 border-b border-ef-line pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-3xl">
            <p class="text-xs font-semibold tracking-[0.24em] text-ef-accent-strong uppercase">Your music, organised</p>
            <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">Album Playlists</h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-ef-muted">Build lists around full albums, arrange them in
                Effemeral, then review and sync them to Spotify when you’re ready.</p>
        </div>
        <a class="inline-flex w-fit items-center border border-ef-accent bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink transition hover:bg-ef-accent-hover"
            href="{{ route('catalogue.index', [], false) }}">Discover Albums <span class="ml-4"
                aria-hidden="true">→</span></a>
    </header>

    @if (session('success'))
        <p class="border border-ef-accent-line bg-ef-surface p-4 text-sm text-ef-text" role="status">
            {{ session('success') }}</p>
    @endif
    @if (session('error'))
        <p class="border border-red-700 bg-ef-surface p-4 text-sm text-ef-text" role="alert">{{ session('error') }}</p>
    @endif

    <dl class="grid border-y border-ef-line bg-ef-surface sm:grid-cols-2">
        <div class="border-b border-ef-line p-5 sm:border-r sm:border-b-0">
            <dt class="text-xs font-semibold tracking-[0.18em] text-ef-muted uppercase">Effemeral Playlists</dt>
            <dd class="mt-3 text-4xl font-semibold tabular-nums tracking-[-0.04em]">{{ number_format($playlistCount) }}</dd>
            <dd class="mt-1 text-sm text-ef-muted">Saved playlists you can manage here</dd>
        </div>
        <div class="p-5">
            <dt class="text-xs font-semibold tracking-[0.18em] text-ef-muted uppercase">Albums Arranged</dt>
            <dd class="mt-3 text-4xl font-semibold tabular-nums tracking-[-0.04em]">{{ number_format($playlistAlbumCount) }}
            </dd>
            <dd class="mt-1 text-sm text-ef-muted">Album placements across your playlists</dd>
        </div>
    </dl>

    <section class="border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="saved-playlists-heading">
        <div class="flex flex-col gap-5 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Your collection</p>
                <h2 id="saved-playlists-heading" class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Your Playlists</h2>
                <p class="mt-2 text-sm text-ef-muted">Open any playlist to add, remove, arrange, and sync albums.</p>
            </div>
            <form class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row" method="POST"
                action="{{ route('album-playlists.store', [], false) }}">
                @csrf
                <label class="sr-only" for="new-album-playlist-name">New playlist name</label>
                <input id="new-album-playlist-name"
                    class="min-w-0 border border-ef-line bg-ef-canvas px-4 py-3 text-sm text-ef-text placeholder:text-ef-muted focus:border-ef-accent"
                    type="text" name="name" maxlength="100" placeholder="e.g. New albums" value="{{ old('name') }}"
                    required>
                <button
                    class="whitespace-nowrap bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                    type="submit">Create Playlist</button>
            </form>
        </div>

        @error('name')
            <p class="mt-4 text-sm text-red-300" role="alert">{{ $message }}</p>
        @enderror

        @if ($albumPlaylists->isNotEmpty())
            <ul class="mt-2 divide-y divide-ef-line">
                @foreach ($albumPlaylists as $albumPlaylist)
                    <li class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="truncate text-lg font-semibold">{{ $albumPlaylist->name }}</h3>
                            <p class="mt-1 text-sm text-ef-muted">
                                {{ $albumPlaylist->albums_count . ' ' . ($albumPlaylist->albums_count === 1 ? 'album' : 'albums') . ' · ' . ucfirst(str_replace('_', ' ', $albumPlaylist->spotify_sync_status)) . ($albumPlaylist->spotify_synced_at ? ' · Synced ' . $albumPlaylist->spotify_synced_at->format('Y.m.d H:i') : '') }}
                            </p>
                            @if ($albumPlaylist->spotify_sync_error)
                                <p class="mt-2 text-sm text-red-300">Spotify sync needs attention:
                                    {{ $albumPlaylist->spotify_sync_error }}</p>
                            @endif
                        </div>
                        <a class="inline-flex shrink-0 items-center font-semibold text-ef-accent-strong hover:underline"
                            href="{{ route('album-playlists.show', $albumPlaylist, false) }}">Manage Playlist <span
                                class="ml-2" aria-hidden="true">→</span></a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mt-6 border border-dashed border-ef-line p-8 text-center">
                <h3 class="text-lg font-semibold">Start Your First Playlist</h3>
                <p class="mt-2 text-sm text-ef-muted">Name a playlist above, then discover albums and add them in full.</p>
            </div>
        @endif
    </section>

    <nav class="flex flex-wrap gap-x-6 gap-y-3 border-t border-ef-line pt-5 text-sm" aria-label="Supporting Tools">
        <a class="font-semibold text-ef-muted hover:text-ef-accent-strong"
            href="{{ route('playlists.index', [], false) }}">Browse Imported Spotify Playlists</a>
        <a class="font-semibold text-ef-muted hover:text-ef-accent-strong"
            href="{{ route('imports.index', [], false) }}">Manage Imports</a>
        <a class="font-semibold text-ef-muted hover:text-ef-accent-strong"
            href="{{ route('recommendations.index', [], false) }}">Album Recommendations</a>
    </nav>
@endsection
