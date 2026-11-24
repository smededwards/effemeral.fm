@extends('layouts.app', ['title' => $albumPlaylist->name . ' · Effemeral'])

@section('content')
    <header class="flex flex-col gap-5 border-b border-ef-line pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a class="text-sm font-semibold text-ef-accent-strong hover:underline"
                href="{{ route('playlists.index', [], false) }}">← All Playlists</a>
            <p class="mt-5 text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Effemeral Playlist</p>
            <div class="mt-2" data-playlist-name-editor>
                <h1 class="text-4xl font-semibold leading-tight tracking-[-0.04em]" data-playlist-name-display>
                    {{ $albumPlaylist->name }}</h1>
                <div class="hidden flex-wrap items-center gap-2" data-playlist-name-form>
                    <form class="contents" method="POST"
                        action="{{ route('album-playlists.update', $albumPlaylist, false) }}">
                        @csrf
                        @method('PATCH')
                        <label class="sr-only" for="playlist-name">Playlist Name</label>
                        <input id="playlist-name"
                            class="playlist-name-input font-serif text-4xl font-semibold leading-tight tracking-[-0.04em] text-ef-text"
                            type="text" name="name" maxlength="100" value="{{ old('name', $albumPlaylist->name) }}"
                            required>
                        <button
                            class="border border-ef-accent px-4 py-2 text-sm font-semibold text-ef-accent-strong hover:bg-ef-accent-wash"
                            type="submit">Save</button>
                        <button
                            class="border border-ef-line px-4 py-2 text-sm font-semibold text-ef-muted hover:border-ef-accent hover:text-ef-text"
                            type="button" data-playlist-name-cancel>Cancel</button>
                    </form>
                    <form class="contents" method="POST"
                        action="{{ route('album-playlists.name.refresh', $albumPlaylist, false) }}">
                        @csrf
                        <button
                            class="border border-ef-line px-4 py-2 text-sm font-semibold text-ef-muted hover:border-ef-accent hover:text-ef-text"
                            type="submit">Refresh</button>
                    </form>
                </div>
            </div>
            <p class="mt-3 text-sm text-ef-muted">{{ $albumPlaylist->albums->count() }}
                {{ $albumPlaylist->albums->count() === 1 ? 'album' : 'albums' }} ·
                {{ ucfirst(str_replace('_', ' ', $albumPlaylist->spotify_sync_status)) }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-4 sm:justify-end">
            <button class="font-semibold text-ef-accent-strong hover:underline" type="button" data-playlist-name-edit>Edit
                Name</button>
            @if ($albumPlaylist->spotify_playlist_url)
                <a class="font-semibold text-ef-accent-strong hover:underline"
                    href="{{ $albumPlaylist->spotify_playlist_url }}" target="_blank" rel="noreferrer">Open in Spotify</a>
            @endif
            <a class="font-semibold text-ef-accent-strong hover:underline"
                href="{{ route('album-playlists.export', $albumPlaylist, false) }}">Export CSV</a>
        </div>
    </header>

    @if (session('success'))
        <p class="border border-ef-accent-line bg-ef-surface p-4 text-sm text-ef-accent-strong" role="status">
            {{ session('success') }}</p>
    @endif
    @if (session('error'))
        <p class="border border-red-800 bg-ef-surface p-4 text-sm text-red-300" role="alert">{{ session('error') }}</p>
    @endif

    <section class="border border-ef-accent-line bg-ef-surface p-5 sm:p-6" aria-labelledby="spotify-sync">
        <div class="flex flex-col gap-3 border-b border-ef-line pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Spotify</p>
                <h2 id="spotify-sync" class="mt-2 text-2xl font-semibold">Review and Sync</h2>
                <p class="mt-2 text-sm text-ef-muted">Effemeral manages only the tracks it adds. Spotify playlist tracks
                    outside that set stay in place.</p>
            </div>
            @if ($albumPlaylist->spotify_synced_at)
                <time class="text-xs tabular-nums text-ef-muted"
                    datetime="{{ $albumPlaylist->spotify_synced_at->toIso8601String() }}">Last synced
                    {{ $albumPlaylist->spotify_synced_at->format('Y.m.d H:i') }}</time>
            @endif
        </div>

        @if ($albumPlaylist->spotify_sync_error)
            <p class="mt-4 border-l-4 border-red-700 bg-ef-canvas px-4 py-3 text-sm text-red-300" role="alert">
                {{ $albumPlaylist->spotify_sync_error }}</p>
        @endif

        @if ($albumPlaylist->spotify_playlist_id)
            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-ef-muted">Linked to <span
                        class="font-semibold text-ef-text">{{ $linkedSpotifyPlaylist?->name ?? 'Spotify playlist' }}</span>
                    @if ($linkedSpotifyPlaylist?->public === true)
                        · Public
                    @elseif ($linkedSpotifyPlaylist?->public === false)
                        · Private
                    @endif
                </p>
                <form method="POST" action="{{ route('album-playlists.spotify.preview', $albumPlaylist, false) }}">
                    @csrf
                    <input type="hidden" name="destination" value="linked">
                    <button class="bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                        type="submit">Review Spotify Changes</button>
                </form>
            </div>
            @if ($albumPlaylist->spotify_playlist_url)
                <a class="mt-3 inline-block text-sm font-semibold text-ef-accent-strong hover:underline"
                    href="{{ $albumPlaylist->spotify_playlist_url }}" target="_blank" rel="noreferrer">Open in Spotify</a>
            @endif
        @else
            <form class="mt-5 grid gap-4 sm:grid-cols-2" method="POST"
                action="{{ route('album-playlists.spotify.preview', $albumPlaylist, false) }}">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-semibold" for="spotify-destination">Destination</label>
                    <select class="w-full border border-ef-line bg-ef-canvas px-3 py-3 text-sm text-ef-text"
                        id="spotify-destination" name="destination">
                        <option value="new">Create a new private playlist</option>
                        <option value="existing">Use an imported playlist</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold" for="spotify-playlist-id">Existing Spotify
                        playlist</label>
                    <select class="w-full border border-ef-line bg-ef-canvas px-3 py-3 text-sm text-ef-text"
                        id="spotify-playlist-id" name="spotify_playlist_id">
                        <option value="">Choose a playlist</option>
                        @foreach ($spotifyPlaylists as $spotifyPlaylist)
                            <option value="{{ $spotifyPlaylist->spotify_playlist_id }}">
                                {{ $spotifyPlaylist->name }}{{ $spotifyPlaylist->public ? ' · Public' : ' · Private' }}{{ $spotifyPlaylist->collaborative ? ' · Collaborative' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-ef-muted">Type a playlist name after opening the list to jump to it.</p>
                </div>
                <div class="sm:col-span-2">
                    <button class="bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                        type="submit">Review Spotify Changes</button>
                </div>
            </form>
        @endif
    </section>

    <section class="border border-ef-line bg-ef-canvas p-5 sm:p-6" aria-labelledby="playlist-albums">
        <div class="border-b border-ef-line pb-4">
            <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Album Order</p>
            <h2 id="playlist-albums" class="mt-2 text-2xl font-semibold">Albums in This Playlist</h2>
            <p class="mt-2 text-sm text-ef-muted">Use the move buttons to set the album order. Albums stay intact.</p>
        </div>
        @if ($albumPlaylist->albums->isNotEmpty())
            <ol class="divide-y divide-ef-line">
                @foreach ($albumPlaylist->albums as $album)
                    @php($isFirst = $loop->first)
                    @php($isLast = $loop->last)
                    <li class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center">
                        <span
                            class="w-8 text-sm font-semibold tabular-nums text-ef-accent-strong">{{ $loop->iteration }}</span>
                        @if ($album->cover_image_url)
                            <img class="h-14 w-14 object-cover" src="{{ $album->cover_image_url }}"
                                alt="Cover art for {{ $album->displayTitle() }}">
                        @endif
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">{{ $album->displayTitle() }}</h3>
                            <p class="mt-1 text-sm text-ef-muted">
                                {{ $album->artists->pluck('name')->implode(', ') ?: 'Unknown artist' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach (['up' => 'Move Up', 'down' => 'Move Down'] as $direction => $label)
                                @if (($direction === 'up' && !$isFirst) || ($direction === 'down' && !$isLast))
                                    <form method="POST"
                                        action="{{ route('album-playlists.albums.move', [$albumPlaylist, $album], false) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="{{ $direction }}">
                                        <button
                                            class="border border-ef-line px-3 py-2 text-xs font-semibold text-ef-text hover:border-ef-accent"
                                            type="submit">{{ $label }}</button>
                                    </form>
                                @endif
                            @endforeach
                            <form method="POST"
                                action="{{ route('album-playlists.albums.destroy', [$albumPlaylist, $album], false) }}">
                                @csrf
                                @method('DELETE')
                                <button
                                    class="border border-ef-line px-3 py-2 text-xs font-semibold text-ef-muted hover:border-red-700 hover:text-red-300"
                                    type="submit">Remove</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ol>
        @else
            <p class="py-6 text-sm text-ef-muted">This playlist is empty. Search your catalogue below to add an album.</p>
        @endif
    </section>

    <section class="border border-ef-line bg-ef-surface p-5 sm:p-6" aria-labelledby="add-albums">
        <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Catalogue</p>
        <h2 id="add-albums" class="mt-2 text-2xl font-semibold">Add Full Albums</h2>
        <form class="mt-4 flex flex-col gap-3 sm:flex-row" method="GET"
            action="{{ route('album-playlists.show', $albumPlaylist, false) }}">
            <label class="sr-only" for="album-search">Search albums or artists</label>
            <input id="album-search"
                class="min-w-0 flex-1 border border-ef-line bg-ef-canvas px-4 py-3 text-sm text-ef-text" type="search"
                name="q" value="{{ $search }}" placeholder="Search albums or artists">
            <button class="bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                type="submit">Search Catalogue</button>
        </form>
        @if ($search !== '')
            @if ($albums->isNotEmpty())
                <form class="mt-4" method="POST"
                    action="{{ route('album-playlists.albums.bulk-store', [], false) }}">
                    @csrf
                    <input type="hidden" name="destination" value="existing">
                    <input type="hidden" name="playlist_id" value="{{ $albumPlaylist->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ef-line pb-3">
                        <label class="flex items-center gap-2 text-sm font-semibold text-ef-text"
                            for="playlist-select-all">
                            <input id="playlist-select-all" type="checkbox" data-playlist-select-all>
                            <span>Select all albums on this page</span>
                        </label>
                        <button
                            class="bg-ef-accent px-4 py-2 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                            type="submit">Add selected albums</button>
                    </div>
                    <ul class="divide-y divide-ef-line">
                        @foreach ($albums as $album)
                            <li class="flex items-start gap-3 py-4">
                                <input class="playlist-album-checkbox mt-1" id="playlist-album-{{ $album->id }}"
                                    type="checkbox" name="album_ids[]" value="{{ $album->id }}">
                                <label class="min-w-0 flex-1 cursor-pointer" for="playlist-album-{{ $album->id }}">
                                    <span class="font-semibold">{{ $album->displayTitle() }}</span>
                                    <span class="mt-1 block text-sm text-ef-muted">
                                        {{ $album->artists->pluck('name')->implode(', ') ?: 'Unknown artist' }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    <button
                        class="mt-4 bg-ef-accent px-4 py-2 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                        type="submit">Add selected albums</button>
                </form>
            @else
                <p class="mt-4 text-sm text-ef-muted">No matching albums found in the catalogue.</p>
            @endif
            @if ($albums->hasPages())
                <div class="mt-4 border-t border-ef-line pt-4">{{ $albums->links() }}</div>
            @endif
        @else
            <p class="mt-4 text-sm text-ef-muted">Search the catalogue to find an album to add.</p>
        @endif
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const selectAll = document.querySelector('[data-playlist-select-all]');
            const albums = [...document.querySelectorAll('.playlist-album-checkbox')];

            if (!selectAll || albums.length === 0) {
                return;
            }

            const updateSelectAll = () => {
                const selected = albums.filter((album) => album.checked).length;
                selectAll.checked = selected === albums.length;
                selectAll.indeterminate = selected > 0 && selected < albums.length;
            };

            selectAll.addEventListener('change', () => {
                albums.forEach((album) => {
                    album.checked = selectAll.checked;
                });
            });
            albums.forEach((album) => album.addEventListener('change', updateSelectAll));
        });
    </script>
@endpush
