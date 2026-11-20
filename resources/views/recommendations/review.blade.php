@extends('layouts.app', ['title' => 'Review playlist draft · Effemeral'])

@section('content')
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-emerald-400">Effemeral</p>
                    <h1 class="mt-3 text-4xl font-semibold">Review playlist draft</h1>
                    <p class="mt-3 max-w-2xl text-zinc-400">Check the exact albums and Spotify tracks before syncing this draft.</p>
                    <p class="mt-2 text-sm text-emerald-300">Source: {{ str($recommendationRun->source)->replace('_', ' ')->title() }}</p>
                </div>
                <a class="text-sm text-[#00e676] hover:text-[#b7ffd1]" href="{{ route('dashboard') }}">Back to dashboard</a>
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
                                @if ($recommendation->album === null)
                                    <p class="mt-2 text-xs text-[#c6ff4a]">Needs catalogue match; review before syncing.</p>
                                @endif
                                @if ($recommendation->match_reason)
                                    <p class="mt-2 text-xs text-emerald-300">{{ $recommendation->match_reason }}</p>
                                @endif
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs {{ $recommendation->included ? 'bg-emerald-950 text-emerald-300' : 'bg-zinc-800 text-zinc-400' }}">{{ $recommendation->included ? 'Included' : 'Excluded' }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="border border-[#355b3d] bg-[#142319] p-6">
                <div>
                    <p class="text-sm text-[#d7ffe2]">Syncing will append {{ $includedTracks }} reviewed tracks to Spotify. Existing tracks are preserved.</p>
                </div>
                @if ($recommendationRun->spotify_playlist_url)
                    <a class="mt-5 inline-block bg-[#00e676] px-4 py-2 text-sm font-semibold text-[#061108] hover:bg-[#b7ffd1]" href="{{ $recommendationRun->spotify_playlist_url }}" target="_blank" rel="noreferrer">Open Spotify playlist</a>
                @endif
                @if ($recommendationRun->status !== 'synced')
                    <form class="mt-6 grid gap-5 border-t border-[#355b3d] pt-6" method="POST" action="{{ route('recommendations.sync', $recommendationRun) }}">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="text-sm text-[#d7ffe2]">
                                <span class="mb-2 block font-semibold">Playlist destination</span>
                                <select class="w-full border border-[#445148] bg-[#0b0c0a] px-3 py-3 text-sm text-[#f3f5f3]" name="playlist_target">
                                    <option value="new">Create a new private playlist</option>
                                    <option value="existing">Use an imported playlist</option>
                                </select>
                            </label>
                            <label class="text-sm text-[#d7ffe2]">
                                <span class="mb-2 block font-semibold">New playlist name</span>
                                <input class="w-full border border-[#445148] bg-[#0b0c0a] px-3 py-3 text-sm text-[#f3f5f3]" type="text" name="playlist_name" value="{{ $playlistNameSuggestion }}" maxlength="100">
                            </label>
                        </div>
                        <label class="text-sm text-[#d7ffe2]">
                            <span class="mb-2 block font-semibold">Imported playlist</span>
                            <select class="w-full border border-[#445148] bg-[#0b0c0a] px-3 py-3 text-sm text-[#f3f5f3]" name="spotify_playlist_id">
                                <option value="">Select an imported playlist when using an existing destination</option>
                                @foreach ($playlists as $playlist)
                                    <option value="{{ $playlist->spotify_playlist_id }}" @selected($recommendationRun->spotify_playlist_id === $playlist->spotify_playlist_id)>{{ $playlist->name }}{{ $playlist->public ? ' · public' : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-[#d7ffe2]">
                            <input class="mt-1" type="checkbox" name="confirmed" value="1" required>
                            <span>I have reviewed the included albums and confirm this playlist should be updated.</span>
                        </label>
                        <button class="w-fit bg-[#00e676] px-4 py-3 text-sm font-semibold text-[#061108] hover:bg-[#b7ffd1]" type="submit">Sync included albums</button>
                    </form>
                @endif
            </section>
@endsection
