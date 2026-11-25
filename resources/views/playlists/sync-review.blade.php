@extends('layouts.app', ['title' => 'Review Spotify Sync · ' . $albumPlaylist->name])

@section('content')
    <header class="border-b border-ef-line pb-8">
        <a class="text-sm font-semibold text-ef-accent-strong hover:underline"
            href="{{ route('album-playlists.show', $albumPlaylist, false) }}">← Back to {{ $albumPlaylist->name }}</a>
        <p class="mt-5 text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Spotify Sync Preview</p>
        <h1 class="mt-2 text-4xl font-semibold tracking-[-0.04em]">Review Playlist Changes</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-ef-muted">Nothing changes in Spotify until you confirm below. Check
            the album order and track availability before continuing.</p>
    </header>

    @if (session('error'))
        <p class="border border-red-800 bg-ef-surface p-4 text-sm text-red-300" role="alert">{{ session('error') }}</p>
    @endif

    <dl class="grid gap-px border border-ef-line bg-ef-line sm:grid-cols-3">
        <div class="bg-ef-surface p-5">
            <dt class="text-xs font-semibold tracking-[0.16em] text-ef-muted uppercase">Effemeral Playlist</dt>
            <dd class="mt-2 font-semibold">{{ $albumPlaylist->name }}</dd>
        </div>
        <div class="bg-ef-surface p-5">
            <dt class="text-xs font-semibold tracking-[0.16em] text-ef-muted uppercase">Spotify Destination</dt>
            <dd class="mt-2 font-semibold">{{ $preview['destination_name'] }}</dd>
            @if ($preview['destination'] === 'new')
                <dd class="mt-1 text-xs text-ef-muted">A new private playlist will be created.</dd>
            @elseif ($preview['remote_name'] !== $albumPlaylist->name)
                <dd class="mt-1 text-xs text-ef-muted">Name change: {{ $preview['remote_name'] }} →
                    {{ $albumPlaylist->name }}</dd>
            @endif
        </div>
        <div class="bg-ef-surface p-5">
            <dt class="text-xs font-semibold tracking-[0.16em] text-ef-muted uppercase">Track Changes</dt>
            <dd class="mt-2 font-semibold">{{ count($preview['add_tracks']) }} to add ·
                {{ count($preview['remove_tracks']) }} to remove</dd>
            <dd class="mt-1 text-xs text-ef-muted">{{ $preview['unmanaged_count'] }} existing Spotify tracks will be kept.
            </dd>
        </div>
    </dl>

    <div class="flex flex-col-reverse gap-3 border-b border-ef-line pb-6 pt-5 sm:flex-row sm:justify-end">
        <a class="border border-ef-line px-5 py-3 text-center text-sm font-semibold text-ef-text hover:border-ef-accent"
            href="{{ route('album-playlists.show', $albumPlaylist, false) }}">Cancel</a>
        <form method="POST" action="{{ route('album-playlists.spotify.sync', $albumPlaylist, false) }}">
            @csrf
            <button
                class="w-full bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                type="submit" @disabled(!$preview['sync_allowed'])>Confirm</button>
        </form>
    </div>

    @if (!$preview['sync_allowed'])
        <section class="border border-amber-500/60 bg-ef-surface p-5 sm:p-6" aria-labelledby="sync-blocked">
            <p class="text-xs font-semibold tracking-[0.16em] text-amber-300 uppercase">Sync blocked</p>
            <h2 id="sync-blocked" class="mt-2 text-xl font-semibold">Complete every album first</h2>
            <p class="mt-2 text-sm text-ef-muted">Spotify tracks are missing from the albums below. Add the missing
                tracks to the catalogue before syncing this playlist.</p>
            <div class="mt-3 flex justify-end">
                <form method="POST"
                    action="{{ route('album-playlists.incomplete-albums.destroy', $albumPlaylist, false) }}"
                    data-confirm="Remove all incomplete albums from this playlist? The catalogue records will remain available.">
                    @csrf
                    @method('DELETE')
                    @foreach ($preview['incomplete_albums'] as $album)
                        <input type="hidden" name="album_ids[]" value="{{ $album['album_id'] }}">
                    @endforeach
                    <button class="border border-red-700 px-3 py-2 text-xs font-semibold text-red-300 hover:bg-red-700/10"
                        type="submit">Remove All</button>
                </form>
            </div>
            <ul class="mt-4 divide-y divide-ef-line">
                @foreach ($preview['incomplete_albums'] as $album)
                    <li class="flex items-center justify-between gap-4 py-3 text-sm">
                        <div>
                            <a class="font-semibold text-ef-accent-strong hover:underline"
                                href="{{ route('albums.show', $album['album_id'], false) }}">{{ $album['title'] }}</a>
                            <p class="mt-1 text-xs text-ef-muted">{{ $album['artist'] }}</p>
                        </div>
                        <span class="shrink-0 text-amber-300">{{ $album['available_tracks'] }} of
                            {{ $album['total_tracks'] }} tracks</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="border border-ef-line bg-ef-surface p-5 sm:p-6" aria-labelledby="album-order">
        <p class="text-xs font-semibold tracking-[0.16em] text-ef-accent-strong uppercase">Playlist Contents</p>
        <h2 id="album-order" class="mt-2 text-2xl font-semibold">Albums in Sync Order</h2>
        <ol class="mt-4 divide-y divide-ef-line">
            @foreach ($preview['albums'] as $index => $album)
                @php($isFirst = $loop->first)
                @php($isLast = $loop->last)
                <li class="flex items-start gap-4 py-4">
                    <span
                        class="w-8 shrink-0 pt-0.5 text-sm font-semibold tabular-nums text-ef-accent-strong">{{ $index + 1 }}</span>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold">{{ $album['title'] }}</h3>
                        <p class="mt-1 text-sm text-ef-muted">{{ $album['artist'] }}</p>
                        @if ($album['total_tracks'] > 0)
                            <p
                                class="mt-1 text-xs {{ $album['available_tracks'] < $album['total_tracks'] ? 'text-amber-300' : 'text-ef-accent-soft' }}">
                                {{ $album['available_tracks'] }} of {{ $album['total_tracks'] }} Spotify tracks available
                            </p>
                        @else
                            <p class="mt-1 text-xs text-amber-300">Album track count is unknown;
                                {{ $album['available_tracks'] }} Spotify tracks matched</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
                        @foreach (['up' => 'Move up', 'down' => 'Move down'] as $direction => $label)
                            @if (($direction === 'up' && !$isFirst) || ($direction === 'down' && !$isLast))
                                <form method="POST"
                                    action="{{ route('album-playlists.albums.move', [$albumPlaylist, $album['album_id']], false) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="{{ $direction }}">
                                    <button
                                        class="border border-ef-line px-3 py-2 text-xs font-semibold text-ef-text hover:border-ef-accent"
                                        type="submit">{{ $label }}</button>
                                </form>
                            @endif
                        @endforeach
                        <form method="POST"
                            action="{{ route('album-playlists.albums.destroy', [$albumPlaylist, $album['album_id']], false) }}">
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
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="border border-ef-accent-line bg-ef-surface p-5 sm:p-6" aria-labelledby="albums-to-add">
            <h2 id="albums-to-add" class="text-xl font-semibold">Albums to Add <span
                    class="text-ef-accent-soft">({{ count($preview['add_albums']) }})</span></h2>
            <p class="mt-2 text-sm text-ef-muted">These albums will contribute {{ count($preview['add_tracks']) }} tracks
                after you confirm the sync.</p>
            @if ($preview['add_albums'] !== [])
                <ol class="mt-4 max-h-[32rem] divide-y divide-ef-line overflow-y-auto">
                    @foreach ($preview['add_albums'] as $album)
                        <li class="py-3">
                            <a class="font-semibold text-ef-accent-strong hover:underline"
                                href="{{ route('albums.show', $album['album_id'], false) }}">{{ $album['title'] }}</a>
                            <p class="mt-1 text-xs text-ef-muted">{{ $album['artist'] }} · {{ $album['track_count'] }}
                                {{ $album['track_count'] === 1 ? 'track' : 'tracks' }}</p>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="mt-4 text-sm text-ef-muted">No tracks need to be added.</p>
            @endif
        </section>

        <section class="border border-ef-line bg-ef-surface p-5 sm:p-6" aria-labelledby="tracks-to-remove">
            <h2 id="tracks-to-remove" class="text-xl font-semibold">Effemeral Tracks to Remove <span
                    class="text-ef-muted">({{ count($preview['remove_tracks']) }})</span></h2>
            <p class="mt-2 text-xs text-ef-muted">Only tracks added by this Effemeral playlist are eligible for removal.</p>
            @if ($preview['remove_tracks'] !== [])
                <ol class="mt-4 max-h-[32rem] divide-y divide-ef-line overflow-y-auto">
                    @foreach ($preview['remove_tracks'] as $track)
                        <li class="py-3">
                            <p class="font-semibold">{{ $track['title'] }}</p>
                            <p class="mt-1 text-xs text-ef-muted">{{ $track['album'] }}</p>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="mt-4 text-sm text-ef-muted">No Effemeral-managed tracks need to be removed.</p>
            @endif
        </section>
    </div>

@endsection
