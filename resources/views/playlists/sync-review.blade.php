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

    <section class="grid gap-px border border-ef-line bg-ef-line sm:grid-cols-3" aria-label="Sync summary">
        <div class="bg-ef-surface p-5">
            <p class="text-xs font-semibold tracking-[0.16em] text-ef-muted uppercase">Effemeral Playlist</p>
            <p class="mt-2 font-semibold">{{ $albumPlaylist->name }}</p>
        </div>
        <div class="bg-ef-surface p-5">
            <p class="text-xs font-semibold tracking-[0.16em] text-ef-muted uppercase">Spotify Destination</p>
            <p class="mt-2 font-semibold">{{ $preview['destination_name'] }}</p>
            @if ($preview['destination'] === 'new')
                <p class="mt-1 text-xs text-ef-muted">A new private playlist will be created.</p>
            @elseif ($preview['remote_name'] !== $albumPlaylist->name)
                <p class="mt-1 text-xs text-ef-muted">Name change: {{ $preview['remote_name'] }} →
                    {{ $albumPlaylist->name }}</p>
            @endif
        </div>
        <div class="bg-ef-surface p-5">
            <p class="text-xs font-semibold tracking-[0.16em] text-ef-muted uppercase">Track Changes</p>
            <p class="mt-2 font-semibold">{{ count($preview['add_tracks']) }} to add ·
                {{ count($preview['remove_tracks']) }} to remove</p>
            <p class="mt-1 text-xs text-ef-muted">{{ $preview['unmanaged_count'] }} existing Spotify tracks will be kept.
            </p>
        </div>
    </section>

    <section class="border border-ef-line bg-ef-surface p-5 sm:p-6" aria-labelledby="album-order">
        <p class="text-xs font-semibold tracking-[0.16em] text-ef-accent-strong uppercase">Playlist Contents</p>
        <h2 id="album-order" class="mt-2 text-2xl font-semibold">Albums in Sync Order</h2>
        <ol class="mt-4 divide-y divide-ef-line">
            @foreach ($preview['albums'] as $index => $album)
                <li class="flex items-start gap-4 py-4">
                    <span
                        class="w-8 shrink-0 pt-0.5 text-sm font-semibold tabular-nums text-ef-accent-strong">{{ $index + 1 }}</span>
                    <div>
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
                </li>
            @endforeach
        </ol>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="border border-ef-accent-line bg-ef-surface p-5 sm:p-6" aria-labelledby="tracks-to-add">
            <h2 id="tracks-to-add" class="text-xl font-semibold">Tracks to Add <span
                    class="text-ef-accent-soft">({{ count($preview['add_tracks']) }})</span></h2>
            @if ($preview['add_tracks'] !== [])
                <ol class="mt-4 max-h-[32rem] divide-y divide-ef-line overflow-y-auto">
                    @foreach ($preview['add_tracks'] as $track)
                        <li class="py-3">
                            <p class="font-semibold">{{ $track['title'] }}</p>
                            <p class="mt-1 text-xs text-ef-muted">{{ $track['artist'] }} · {{ $track['album'] }}</p>
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

    <footer class="flex flex-col-reverse gap-3 border-t border-ef-line pt-6 sm:flex-row sm:justify-end">
        <a class="border border-ef-line px-5 py-3 text-center text-sm font-semibold text-ef-text hover:border-ef-accent"
            href="{{ route('album-playlists.show', $albumPlaylist, false) }}">Cancel</a>
        <form method="POST" action="{{ route('album-playlists.spotify.sync', $albumPlaylist, false) }}">
            @csrf
            <button
                class="w-full bg-ef-accent px-5 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover sm:w-auto"
                type="submit">Confirm and Sync to Spotify</button>
        </form>
    </footer>
@endsection
