@extends('layouts.app', ['title' => 'Effemeral Playlists · Effemeral'])

@section('content')
    <header class="flex flex-col gap-5 border-b border-ef-line pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold tracking-[0.24em] text-ef-accent-strong uppercase">Playlists</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Effemeral Playlists</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-[#9aa39d]">Build, arrange, and sync full-album playlists.
                Your drafts stay here until you choose to send them to Spotify.</p>
        </div>
        <a class="border border-ef-line px-5 py-3 text-sm font-semibold text-ef-accent-soft hover:border-ef-accent"
            href="{{ route('spotify-playlists.index', [], false) }}">Browse Spotify Archive</a>
    </header>

    <section class="border border-ef-accent-line bg-ef-surface p-6 sm:p-8" aria-labelledby="create-playlist">
        <div class="flex flex-col gap-4 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Create</p>
                <h2 id="create-playlist" class="mt-2 text-2xl font-semibold">Effemeral Album Playlists</h2>
                <p class="mt-2 text-sm text-ef-muted">Add albums in Effemeral, review the order, then sync when it is ready.
                </p>
            </div>
            <form class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row" method="POST"
                action="{{ route('album-playlists.store', [], false) }}">
                @csrf
                <label class="sr-only" for="new-album-playlist-name">New playlist name</label>
                <input id="new-album-playlist-name"
                    class="min-w-0 border border-ef-line bg-ef-canvas px-4 py-3 text-sm text-ef-text" type="text"
                    name="name" maxlength="100" placeholder="Optional name">
                <button class="bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                    type="submit">Create Playlist</button>
            </form>
        </div>
    </section>

    <section class="border border-ef-line bg-ef-canvas p-6 sm:p-8" aria-labelledby="playlist-list">
        <div class="flex flex-col gap-3 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Your Collection</p>
                <h2 id="playlist-list" class="mt-2 text-2xl font-semibold">Your Playlists</h2>
            </div>
            <form method="GET" action="{{ route('playlists.index', [], false) }}">
                <label class="sr-only" for="playlist-sort">Sort playlists</label>
                <select id="playlist-sort" class="min-w-48" name="playlist_sort" onchange="this.form.submit()">
                    <option value="recent" @selected($playlistSort === 'recent')>Recently Updated</option>
                    <option value="oldest" @selected($playlistSort === 'oldest')>Oldest Updated</option>
                    <option value="a_z" @selected($playlistSort === 'a_z')>A–Z</option>
                    <option value="z_a" @selected($playlistSort === 'z_a')>Z–A</option>
                </select>
            </form>
        </div>
        @if ($albumPlaylists->isNotEmpty())
            <ul class="mt-4 divide-y divide-ef-line">
                @foreach ($albumPlaylists as $albumPlaylist)
                    <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold"><a class="hover:text-ef-accent-strong hover:underline"
                                    href="{{ route('album-playlists.show', $albumPlaylist, false) }}">{{ $albumPlaylist->name }}</a>
                            </h3>
                            <p class="mt-1 text-sm text-ef-muted">{{ $albumPlaylist->albums_count }}
                                {{ $albumPlaylist->albums_count === 1 ? 'album' : 'albums' }} ·
                                {{ ucfirst(str_replace('_', ' ', $albumPlaylist->spotify_sync_status)) }}</p>
                        </div>
                        <a class="font-semibold text-ef-accent-strong hover:underline"
                            href="{{ route('album-playlists.show', $albumPlaylist, false) }}">Manage Playlist →</a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-5 text-sm text-ef-muted">No Effemeral playlists yet. Create one to start arranging full albums.</p>
        @endif
    </section>
@endsection
