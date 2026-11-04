<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Review playlist draft · Effemeral</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100">
        <main class="mx-auto max-w-5xl space-y-8 px-6 py-12">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-emerald-400">Effemeral</p>
                    <h1 class="mt-3 text-4xl font-semibold">Review playlist draft</h1>
                    <p class="mt-3 max-w-2xl text-zinc-400">Check the exact albums and Spotify tracks before syncing this draft.</p>
                </div>
                <a class="text-sm text-[#e56cb0] hover:text-[#f08cc4]" href="{{ route('dashboard') }}">Back to dashboard</a>
            </header>

            @if (session('success'))
                <div class="rounded-lg border border-emerald-800 bg-emerald-950/60 p-4 text-emerald-200">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="rounded-lg border border-red-800 bg-red-950/60 p-4 text-red-200">{{ session('error') }}</div>
            @endif

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-500">Included albums</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $includedAlbums }}</p>
                </div>
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-500">Excluded albums</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $excludedAlbums }}</p>
                </div>
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                    <p class="text-sm text-zinc-500">Spotify tracks to sync</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $includedTracks }}</p>
                </div>
            </section>

            <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-medium">Album order</h2>
                        <p class="mt-1 text-sm text-zinc-400">Included albums will be synced in this order.</p>
                    </div>
                    <span class="text-sm capitalize text-zinc-500">{{ $recommendationRun->status }}</span>
                </div>

                <ol class="mt-6 space-y-3">
                    @foreach ($reviewAlbums as $item)
                        @php($recommendation = $item['recommendation'])
                        <li class="flex items-center gap-4 rounded-lg border border-zinc-800 bg-zinc-950 p-4">
                            <span class="w-8 text-center text-lg text-zinc-500">{{ $recommendation->position }}</span>
                            @if ($recommendation->spotifySavedAlbum->cover_image_url)
                                <img class="h-16 w-16 rounded object-cover" src="{{ $recommendation->spotifySavedAlbum->cover_image_url }}" alt="Cover art for {{ $recommendation->spotifySavedAlbum->name }}">
                            @else
                                <div class="flex h-16 w-16 items-center justify-center rounded bg-zinc-900 text-xs text-zinc-600">No artwork</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <h3 class="font-medium">{{ $recommendation->spotifySavedAlbum->name }}</h3>
                                <p class="mt-1 text-sm text-zinc-400">{{ $recommendation->spotifySavedAlbum->primary_artist_name ?? 'Unknown artist' }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $item['track_count'] }} Spotify tracks available</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs {{ $recommendation->included ? 'bg-emerald-950 text-emerald-300' : 'bg-zinc-800 text-zinc-400' }}">{{ $recommendation->included ? 'Included' : 'Excluded' }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-900/60 bg-amber-950/20 p-6">
                <p class="text-sm text-amber-100">Syncing will create a private Spotify playlist containing {{ $includedTracks }} tracks.</p>
                @if ($recommendationRun->spotify_playlist_url)
                    <a class="rounded-md bg-emerald-500 px-4 py-2 text-sm font-medium text-zinc-950" href="{{ $recommendationRun->spotify_playlist_url }}" target="_blank" rel="noreferrer">Open Spotify playlist</a>
                @else
                    <form method="POST" action="{{ route('recommendations.sync', $recommendationRun) }}">
                        @csrf
                        <label class="mb-3 flex items-start gap-2 text-sm text-amber-100">
                            <input class="mt-1" type="checkbox" name="confirmed" value="1" required>
                            <span>I have reviewed the included albums and confirm this private Spotify playlist should be created.</span>
                        </label>
                        <button class="rounded-md bg-[#BF3989] px-4 py-2 text-sm font-medium text-white hover:bg-[#a93278]" type="submit">Sync included albums</button>
                    </form>
                @endif
            </section>
        </main>
    </body>
</html>
