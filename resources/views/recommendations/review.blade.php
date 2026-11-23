@extends('layouts.app', ['title' => 'Review Playlist Draft · Effemeral'])

@section('content')
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm tracking-[0.3em] text-ef-accent-strong">Effemeral</p>
                    <h1 class="mt-3 text-4xl font-semibold">Review Playlist Draft</h1>
                    <p class="mt-3 max-w-2xl text-ef-muted">Check the exact albums and Spotify tracks before syncing this draft.</p>
                    <p class="mt-2 text-sm text-ef-accent-strong">Source: {{ ucfirst(str_replace('_', ' ', $recommendationRun->source)) }}</p>
                </div>
                <a class="text-sm text-ef-accent-strong hover:text-ef-accent-soft" href="{{ route('dashboard', [], false) }}">Back to Dashboard</a>
            </header>

            @if (session('success'))
                <div class="rounded-lg border border-ef-accent-line bg-ef-accent-wash/60 p-4 text-ef-accent-soft">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="rounded-lg border border-red-800 bg-red-950/60 p-4 text-red-200">{{ session('error') }}</div>
            @endif

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-ef-line bg-ef-surface p-5">
                    <p class="text-sm text-ef-muted">Included albums</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $includedAlbums }}</p>
                </div>
                <div class="rounded-xl border border-ef-line bg-ef-surface p-5">
                    <p class="text-sm text-ef-muted">Excluded albums</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $excludedAlbums }}</p>
                </div>
                <div class="rounded-xl border border-ef-line bg-ef-surface p-5">
                    <p class="text-sm text-ef-muted">Spotify tracks to sync</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $includedTracks }}</p>
                </div>
            </section>

            <section class="rounded-xl border border-ef-line bg-ef-surface p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-medium">Album Order</h2>
                        <p class="mt-1 text-sm text-ef-muted">Included albums will be synced in this order.</p>
                    </div>
                    <span class="text-sm text-ef-muted">{{ ucfirst($recommendationRun->status) }}</span>
                </div>

                <ol class="mt-6 space-y-3">
                    @foreach ($reviewAlbums as $item)
                        @php($recommendation = $item['recommendation'])
                        <li class="flex items-center gap-4 rounded-lg border border-ef-line bg-ef-canvas p-4">
                            <span class="w-8 text-center text-lg text-ef-muted">{{ $recommendation->position }}</span>
                            @if ($recommendation->spotifySavedAlbum?->cover_image_url ?? $recommendation->album?->cover_image_url)
                                <img class="h-16 w-16 rounded object-cover" src="{{ $recommendation->spotifySavedAlbum?->cover_image_url ?? $recommendation->album?->cover_image_url }}" alt="Cover art for {{ $recommendation->spotifySavedAlbum?->name ?? $recommendation->album?->title }}">
                            @else
                                <div class="flex h-16 w-16 items-center justify-center rounded bg-ef-surface text-xs text-ef-muted">No artwork</div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <h3 class="font-medium">{{ $recommendation->spotifySavedAlbum?->name ?? $recommendation->album?->title ?? 'Unknown album' }}</h3>
                                <p class="mt-1 text-sm text-ef-muted">{{ $recommendation->spotifySavedAlbum?->primary_artist_name ?? $recommendation->album?->artists->first()?->name ?? 'Unknown artist' }}</p>
                                <p class="mt-1 text-xs text-ef-muted">{{ $item['track_count'] }} Spotify tracks available</p>
                                @if ($recommendation->album === null)
                                    <p class="mt-2 text-xs text-ef-accent-strong">Needs catalogue match; review before syncing.</p>
                                @endif
                                @if ($recommendation->match_reason)
                                    <p class="mt-2 text-xs text-ef-accent-strong">{{ $recommendation->match_reason }}</p>
                                @endif
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs {{ $recommendation->included ? 'bg-ef-accent-wash text-ef-accent-strong' : 'bg-ef-surface-raised text-ef-muted' }}">{{ $recommendation->included ? 'Included' : 'Excluded' }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="border border-ef-accent-line bg-ef-accent-wash p-6">
                <div>
                    <p class="text-sm text-ef-accent-soft">Syncing will append {{ $includedTracks }} reviewed tracks to Spotify. Existing tracks are preserved.</p>
                </div>
                @if ($recommendationRun->spotify_playlist_url)
                    <a class="mt-5 inline-block bg-ef-accent px-4 py-2 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-soft" href="{{ $recommendationRun->spotify_playlist_url }}" target="_blank" rel="noreferrer">Open Spotify Playlist</a>
                @endif
                @if ($recommendationRun->status !== 'synced')
                    <form class="mt-6 grid gap-5 border-t border-ef-accent-line pt-6" method="POST" action="{{ route('recommendations.sync', $recommendationRun, false) }}">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="text-sm text-ef-accent-soft">
                                <span class="mb-2 block font-semibold">Playlist destination</span>
                                <select class="w-full border border-ef-line bg-ef-canvas px-3 py-3 text-sm text-ef-text" name="playlist_target">
                                    <option value="new">Create a new private playlist</option>
                                    <option value="existing">Use an imported playlist</option>
                                </select>
                            </label>
                            <label class="text-sm text-ef-accent-soft">
                                <span class="mb-2 block font-semibold">New playlist name</span>
                                <input class="w-full border border-ef-line bg-ef-canvas px-3 py-3 text-sm text-ef-text" type="text" name="playlist_name" value="{{ $playlistNameSuggestion }}" maxlength="100">
                            </label>
                        </div>
                        <label class="text-sm text-ef-accent-soft">
                            <span class="mb-2 block font-semibold">Imported playlist</span>
                            <select class="w-full border border-ef-line bg-ef-canvas px-3 py-3 text-sm text-ef-text" name="spotify_playlist_id">
                                <option value="">Select an imported playlist when using an existing destination</option>
                                @foreach ($playlists as $playlist)
                                    <option value="{{ $playlist->spotify_playlist_id }}" @selected($recommendationRun->spotify_playlist_id === $playlist->spotify_playlist_id)>{{ $playlist->name }}{{ $playlist->public ? ' · public' : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-ef-accent-soft">
                            <input class="mt-1" type="checkbox" name="confirmed" value="1" required>
                            <span>I have reviewed the included albums and confirm this playlist should be updated.</span>
                        </label>
                        <button class="w-fit bg-ef-accent px-4 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-soft" type="submit">Sync included albums</button>
                    </form>
                @endif
            </section>
@endsection
