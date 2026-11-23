@extends('layouts.app', ['title' => 'Imports · Effemeral'])

@section('content')
    <header class="flex flex-col gap-4 border-b border-ef-line pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold tracking-[0.24em] text-ef-accent-strong uppercase">Imports / 02</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Keep the Sources Current</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-ef-muted">Last.fm supplies listening evidence. Spotify supplies
                the working library and playlist context.</p>
        </div>
        <span class="text-xs tracking-[0.16em] text-ef-muted">Manual sync</span>
    </header>

    <div class="grid gap-px border border-ef-line bg-ef-line lg:grid-cols-2">
        <section class="bg-ef-canvas p-6 sm:p-8" aria-labelledby="lastfm-history-heading">
            <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Source 01</p>
            <h2 id="lastfm-history-heading" class="mt-3 text-2xl font-semibold">Last.fm History</h2>
            <p class="mt-2 text-sm leading-6 text-ef-muted">Import a bounded range while testing, or queue your complete
                Last.fm history.</p>
            <form class="mt-8 space-y-5" method="POST" action="{{ route('imports.lastfm', [], false) }}">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm text-ef-accent-soft">From<input
                            class="mt-2 block w-full border border-ef-line bg-ef-surface px-3 py-3 text-ef-text"
                            type="date" name="from" value="{{ old('from', $defaultFrom) }}" required></label><label
                        class="block text-sm text-ef-accent-soft">To<input
                            class="mt-2 block w-full border border-ef-line bg-ef-surface px-3 py-3 text-ef-text"
                            type="date" name="to" value="{{ old('to', $defaultTo) }}" required></label></div>
                <label class="block text-sm text-ef-accent-soft">Maximum scrobbles<input
                        class="mt-2 block w-full border border-ef-line bg-ef-surface px-3 py-3 text-ef-text" type="number"
                        name="limit" min="1" max="1000" value="{{ old('limit', 100) }}" required></label>
                <div class="flex flex-wrap gap-3"><button
                        class="bg-ef-accent px-4 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                        type="submit">Queue import</button><button
                        class="border border-ef-accent px-4 py-3 text-sm font-semibold text-ef-accent-strong hover:bg-ef-accent-wash"
                        type="submit" formaction="{{ route('imports.lastfm.full', [], false) }}">Queue full import</button>
                </div>
            </form>
        </section>
        <section class="bg-ef-surface p-6 sm:p-8" aria-labelledby="spotify-library-heading">
            <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Source 02</p>
            <h2 id="spotify-library-heading" class="mt-3 text-2xl font-semibold">Spotify Library</h2>
            <p class="mt-2 text-sm leading-6 text-ef-muted">Connect once, then refresh saved albums, liked songs, and
                playlists whenever you choose.</p>
            <p class="mt-8 border-y border-ef-line py-4 text-sm text-ef-accent-soft">
                {{ $spotifyConnection ? 'Connected to Spotify.' : 'Spotify is not connected.' }}</p>
            @if ($spotifyConnection)
                <p class="mt-4 text-xs leading-5 text-ef-muted">Reconnect Spotify after this update to approve playlist
                    editing permissions.</p>
                <div class="mt-4 flex flex-wrap items-center gap-4">
                    <a class="inline-block bg-ef-accent px-4 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                        href="{{ route('auth.spotify', [], false) }}">Reconnect Spotify</a>
                    <form method="POST" action="{{ route('auth.spotify.disconnect', [], false) }}">@csrf<button
                            class="border border-ef-line px-4 py-3 text-sm font-semibold text-ef-text hover:border-ef-accent"
                            type="submit">Disconnect Spotify</button></form>
                </div>
            @else
                <a class="mt-6 inline-block bg-ef-accent px-4 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                    href="{{ route('auth.spotify', [], false) }}">Connect Spotify</a>
            @endif
        </section>
    </div>

    <section class="border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="library-imports-heading">
        <div class="flex flex-col gap-4 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-muted">Spotify</p>
                <h2 id="library-imports-heading" class="mt-2 text-2xl font-semibold">Library Imports</h2>
            </div><span class="text-xs text-ef-muted">Supporting catalogue data</span>
        </div>
        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <form method="POST" action="{{ route('imports.spotify.albums', [], false) }}">@csrf<button
                    class="w-full bg-ef-accent px-4 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                    type="submit">Saved albums</button></form>
            <form method="POST" action="{{ route('imports.spotify.liked-tracks', [], false) }}">@csrf<button
                    class="w-full border border-ef-accent px-4 py-3 text-sm font-semibold text-ef-accent-strong hover:bg-ef-accent-wash"
                    type="submit">Liked songs</button></form>
            <form method="POST" action="{{ route('imports.spotify.playlists', [], false) }}">@csrf<button
                    class="w-full border border-ef-line px-4 py-3 text-sm font-semibold text-ef-text hover:border-ef-accent"
                    type="submit">Playlists</button></form>
        </div>
        @if ($latestSpotifyImport)
            <p class="mt-6 text-sm text-ef-muted">Latest library import: <span
                    class="font-semibold text-ef-accent-soft">{{ ucfirst(str_replace('_', ' ', $latestSpotifyImport->status)) }}</span>
                · {{ number_format($latestSpotifyImport->next_offset) }} of
                {{ $latestSpotifyImport->total_count === null ? 'an unknown total' : number_format($latestSpotifyImport->total_count) }}
                albums reached · {{ number_format($latestSpotifyImport->fetched_count) }} fetched.</p>
            @endif @if ($latestSpotifyPlaylistImport)
                <p class="mt-2 text-sm text-ef-muted">Latest playlist import: <span
                        class="font-semibold text-ef-accent-soft">{{ ucfirst(str_replace('_', ' ', $latestSpotifyPlaylistImport->status)) }}</span>
                    · {{ number_format($latestSpotifyPlaylistImport->next_offset) }} of
                    {{ $latestSpotifyPlaylistImport->total_count === null ? 'an unknown total' : number_format($latestSpotifyPlaylistImport->total_count) }}
                    playlists reached · {{ number_format($latestSpotifyPlaylistImport->fetched_count) }} tracks.</p>
            @endif
    </section>
    <section class="border border-ef-line bg-ef-canvas p-6 sm:p-8" aria-labelledby="lastfm-imports-heading">
        <div class="flex items-end justify-between gap-4 border-b border-ef-line pb-5">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-muted">History</p>
                <h2 id="lastfm-imports-heading" class="mt-2 text-2xl font-semibold">Recent Last.fm Imports</h2>
            </div><span class="text-sm tabular-nums text-ef-muted">{{ number_format($scrobbleCount) }} scrobbles</span>
        </div>
        <ul class="divide-y divide-ef-line">
            @forelse ($recentImports as $import)
                <li class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-ef-text">{{ $import->created_at?->format('Y.m.d H:i') }}</p>
                        <p class="mt-1 text-xs text-ef-muted">
                            {{ $import->requested_limit ? 'Bounded import' : 'Full import' }} ·
                            {{ number_format($import->fetched_count) }} fetched ·
                            {{ number_format($import->failed_count) }} failed</p>
                    </div>
                    <div class="flex items-center gap-4 text-sm"><span
                            class="text-ef-accent-soft">{{ ucfirst(str_replace('_', ' ', $import->status)) }}</span>
                        @if ($import->status === 'failed')
                            <form method="POST" action="{{ route('imports.lastfm.retry', $import, false) }}">@csrf<button
                                    class="font-semibold text-ef-accent-strong hover:underline"
                                    type="submit">Retry</button></form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="py-6 text-sm text-ef-muted">No import history yet.</li>
            @endforelse
        </ul>
    </section>
@endsection
