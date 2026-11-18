@extends('layouts.app', ['title' => 'Imports · Effemeral'])

@section('content')
    <header>
        <p class="text-sm uppercase tracking-[0.3em] text-emerald-400">Effemeral</p>
        <h1 class="mt-3 text-4xl font-semibold">Imports</h1>
        <p class="mt-3 max-w-2xl text-zinc-400">Bring Last.fm history and Spotify library data into the canonical catalogue.</p>
    </header>

    <section class="grid gap-6 md:grid-cols-2">
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
            <h2 class="text-xl font-medium">Last.fm</h2>
            <form class="mt-6 space-y-4" method="POST" action="{{ route('imports.lastfm') }}">
                @csrf
                <label class="block text-sm text-zinc-300">From<input class="mt-2 block w-full rounded-md border-zinc-700 bg-zinc-950 text-zinc-100" type="date" name="from" value="{{ old('from', $defaultFrom) }}" required></label>
                <label class="block text-sm text-zinc-300">To<input class="mt-2 block w-full rounded-md border-zinc-700 bg-zinc-950 text-zinc-100" type="date" name="to" value="{{ old('to', $defaultTo) }}" required></label>
                <label class="block text-sm text-zinc-300">Maximum scrobbles<input class="mt-2 block w-full rounded-md border-zinc-700 bg-zinc-950 text-zinc-100" type="number" name="limit" min="1" max="1000" value="{{ old('limit', 100) }}" required></label>
                <div class="flex flex-wrap gap-3">
                    <button class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950" type="submit">Queue import</button>
                    <button class="rounded-md border border-zinc-700 px-4 py-2 font-medium text-zinc-200" type="submit" formaction="{{ route('imports.lastfm.full') }}">Queue full import</button>
                </div>
            </form>
        </div>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
            <h2 class="text-xl font-medium">Spotify account</h2>
            <p class="mt-2 text-sm text-zinc-400">{{ $spotifyConnection ? 'Connected'.($spotifyConnection->display_name ? ' as '.$spotifyConnection->display_name : '').'.' : 'Connect Spotify to import library data.' }}</p>
            @if ($spotifyConnection)
                <form class="mt-6" method="POST" action="{{ route('auth.spotify.disconnect') }}">@csrf<button class="rounded-md border border-zinc-700 px-4 py-2 font-medium text-zinc-200" type="submit">Disconnect Spotify</button></form>
            @else
                <a class="mt-6 inline-block rounded-md bg-[#BF3989] px-4 py-2 font-medium text-white" href="{{ route('auth.spotify') }}">Connect Spotify</a>
            @endif
        </div>
    </section>

    <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-xl font-medium">Spotify library imports</h2><p class="mt-1 text-sm text-zinc-400">Import saved albums, liked songs, or playlists as supporting catalogue data.</p></div><div class="flex flex-wrap gap-3"><form method="POST" action="{{ route('imports.spotify.albums') }}">@csrf<button class="rounded-md bg-[#BF3989] px-4 py-2 text-sm font-medium text-white" type="submit">Saved albums</button></form><form method="POST" action="{{ route('imports.spotify.liked-tracks') }}">@csrf<button class="rounded-md border border-[#BF3989] px-4 py-2 text-sm font-medium text-[#f08cc4]" type="submit">Liked songs</button></form><form method="POST" action="{{ route('imports.spotify.playlists') }}">@csrf<button class="rounded-md border border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-200" type="submit">Playlists</button></form></div></div>
        @if ($latestSpotifyImport)<p class="mt-5 text-sm text-zinc-400">Latest library import: <span class="capitalize">{{ $latestSpotifyImport->status }}</span> · {{ number_format($latestSpotifyImport->fetched_count) }} fetched.</p>@endif
        @if ($latestSpotifyPlaylistImport)<p class="mt-2 text-sm text-zinc-400">Latest playlist import: <span class="capitalize">{{ $latestSpotifyPlaylistImport->status }}</span> · {{ number_format($latestSpotifyPlaylistImport->fetched_count) }} tracks.</p>@endif
    </section>

    <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6"><h2 class="text-xl font-medium">Recent Last.fm imports</h2><p class="mt-1 text-sm text-zinc-400">{{ number_format($scrobbleCount) }} scrobbles stored.</p><div class="mt-5 space-y-2">@forelse ($recentImports as $import)<div class="flex flex-wrap justify-between gap-3 border-b border-zinc-800 pb-2 text-sm"><span>{{ $import->created_at?->format('Y-m-d H:i') }}</span><span class="capitalize text-zinc-400">{{ $import->status }}</span>@if ($import->status === 'failed')<form method="POST" action="{{ route('imports.lastfm.retry', $import) }}">@csrf<button class="text-emerald-400" type="submit">Retry</button></form>@endif</div>@empty<p class="text-sm text-zinc-500">No import history yet.</p>@endforelse</div></section>
@endsection
