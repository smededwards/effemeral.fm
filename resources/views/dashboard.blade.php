<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Effemeral</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100">
        <main class="mx-auto max-w-5xl space-y-8 px-6 py-12">
            <header>
                <p class="text-sm uppercase tracking-[0.3em] text-emerald-400">Effemeral</p>
                <h1 class="mt-3 text-4xl font-semibold">Listening history</h1>
                <p class="mt-3 max-w-2xl text-zinc-400">Import Last.fm scrobbles manually while the matching and recommendation tools are built.</p>
            </header>

            @if (session('success'))
                <div class="rounded-lg border border-emerald-800 bg-emerald-950/60 p-4 text-emerald-200">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="rounded-lg border border-red-800 bg-red-950/60 p-4 text-red-200">{{ session('error') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-800 bg-red-950/60 p-4 text-red-200">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="grid gap-6 md:grid-cols-2">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                    <h2 class="text-xl font-medium">Import Last.fm</h2>
                    <p class="mt-2 text-sm text-zinc-400">The first prototype defaults to the most recent 90 days and 100 scrobbles.</p>

                    <form class="mt-6 space-y-4" method="POST" action="{{ route('imports.lastfm') }}">
                        @csrf
                        <label class="block text-sm text-zinc-300">
                            From
                            <input class="mt-2 block w-full rounded-md border-zinc-700 bg-zinc-950 text-zinc-100" type="date" name="from" value="{{ old('from', $defaultFrom) }}" required>
                        </label>
                        <label class="block text-sm text-zinc-300">
                            To
                            <input class="mt-2 block w-full rounded-md border-zinc-700 bg-zinc-950 text-zinc-100" type="date" name="to" value="{{ old('to', $defaultTo) }}" required>
                        </label>
                        <label class="block text-sm text-zinc-300">
                            Maximum scrobbles
                            <input class="mt-2 block w-full rounded-md border-zinc-700 bg-zinc-950 text-zinc-100" type="number" name="limit" min="1" max="1000" value="{{ old('limit', 100) }}" required>
                        </label>
                        <div class="flex flex-wrap gap-3">
                            <button class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400" type="submit">Queue import</button>
                            <button class="rounded-md border border-zinc-700 px-4 py-2 font-medium text-zinc-200 hover:border-zinc-500" type="submit" formaction="{{ route('imports.lastfm.full') }}">Queue full import</button>
                        </div>
                        <p class="text-xs text-zinc-500">Full import starts at {{ config('services.lastfm.import_start') }} and continues through today. It may take much longer.</p>
                    </form>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                    <h2 class="text-xl font-medium">Import status</h2>
                    <dl class="mt-6 space-y-4 text-sm">
                        <div class="flex justify-between gap-4 border-b border-zinc-800 pb-3"><dt class="text-zinc-400">Stored scrobbles</dt><dd>{{ number_format($scrobbleCount) }}</dd></div>
                        @if ($latestImport)
                            <div class="flex justify-between gap-4 border-b border-zinc-800 pb-3"><dt class="text-zinc-400">Latest status</dt><dd class="capitalize">{{ $latestImport->status }}</dd></div>
                            <div class="flex justify-between gap-4 border-b border-zinc-800 pb-3"><dt class="text-zinc-400">Fetched</dt><dd>{{ number_format($latestImport->fetched_count) }}</dd></div>
                            <div class="flex justify-between gap-4 border-b border-zinc-800 pb-3"><dt class="text-zinc-400">Imported</dt><dd>{{ number_format($latestImport->imported_count) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-zinc-400">Skipped duplicates</dt><dd>{{ number_format($latestImport->skipped_count) }}</dd></div>
                            @if ($latestImport->error)
                                <p class="rounded-md bg-red-950/60 p-3 text-red-200">{{ $latestImport->error }}</p>
                            @endif
                        @else
                            <p class="text-zinc-400">No imports have been run yet.</p>
                        @endif
                    </dl>
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                    <h2 class="text-xl font-medium">Spotify account</h2>
                    @if ($spotifyConnection)
                        <p class="mt-2 text-sm text-zinc-400">Connected{{ $spotifyConnection->display_name ? ' as '.$spotifyConnection->display_name : '' }}.</p>
                        <form class="mt-6" method="POST" action="{{ route('auth.spotify.disconnect') }}">
                            @csrf
                            <button class="rounded-md border border-zinc-700 px-4 py-2 font-medium text-zinc-200 hover:border-zinc-500" type="submit">Disconnect Spotify</button>
                        </form>
                    @else
                        <p class="mt-2 text-sm text-zinc-400">Connect Spotify to import your saved albums.</p>
                        <a class="mt-6 inline-block rounded-md bg-[#BF3989] px-4 py-2 font-medium text-white hover:bg-[#a93278]" href="{{ route('auth.spotify') }}">Connect Spotify</a>
                    @endif
                </div>
            </section>

            <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-medium">Spotify playlists</h2>
                        <p class="mt-1 text-sm text-zinc-400">Audit your ten most recent playlists by album and track membership.</p>
                    </div>
                    <form method="POST" action="{{ route('imports.spotify.playlists') }}">
                        @csrf
                        <button class="rounded-md bg-[#BF3989] px-4 py-2 text-sm font-medium text-white hover:bg-[#a93278]" type="submit">Import playlists</button>
                    </form>
                </div>

                @if ($latestSpotifyPlaylistImport)
                    <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-4">
                        <div><dt class="text-zinc-500">Status</dt><dd class="mt-1 capitalize">{{ $latestSpotifyPlaylistImport->status }}</dd></div>
                        <div><dt class="text-zinc-500">Playlist rows</dt><dd class="mt-1">{{ number_format($latestSpotifyPlaylistImport->imported_count + $latestSpotifyPlaylistImport->updated_count) }}</dd></div>
                        <div><dt class="text-zinc-500">Track items</dt><dd class="mt-1">{{ number_format($latestSpotifyPlaylistImport->fetched_count) }}</dd></div>
                        <div><dt class="text-zinc-500">Skipped</dt><dd class="mt-1">{{ number_format($latestSpotifyPlaylistImport->skipped_count) }}</dd></div>
                    </dl>
                @endif

                @if ($spotifyPlaylists->isNotEmpty())
                    <div class="mt-6 space-y-3">
                        @foreach ($spotifyPlaylists as $playlist)
                            <article class="rounded-lg border border-zinc-800 bg-zinc-950 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-medium">{{ $playlist->name }}</h3>
                                        <p class="mt-1 text-sm text-zinc-400">{{ $playlist->items->count() }} imported tracks · {{ $playlist->items->pluck('album_id')->filter()->unique()->count() }} albums</p>
                                    </div>
                                    @if ($playlist->spotify_url)
                                        <a class="text-sm text-[#e56cb0] hover:text-[#f08cc4]" href="{{ $playlist->spotify_url }}" target="_blank" rel="noreferrer">Open in Spotify</a>
                                    @endif
                                </div>
                                <p class="mt-3 text-xs text-zinc-500">{{ $playlist->description ?: 'No description' }}</p>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="mt-6 text-sm text-zinc-500">No Spotify playlists have been imported yet.</p>
                @endif
            </section>

            <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-medium">Spotify saved albums</h2>
                        <p class="mt-1 text-sm text-zinc-400">Import the 100 most recently saved albums as supporting library data.</p>
                    </div>
                    <form method="POST" action="{{ route('imports.spotify.albums') }}">
                        @csrf
                        <button class="rounded-md bg-[#BF3989] px-4 py-2 font-medium text-white hover:bg-[#a93278]" type="submit">Queue Spotify import</button>
                    </form>
                </div>
                @if ($latestSpotifyImport)
                    <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-4">
                        <div><dt class="text-zinc-400">Status</dt><dd class="mt-1 capitalize">{{ $latestSpotifyImport->status }}</dd></div>
                        <div><dt class="text-zinc-400">Fetched</dt><dd class="mt-1">{{ number_format($latestSpotifyImport->fetched_count) }}</dd></div>
                        <div><dt class="text-zinc-400">Imported</dt><dd class="mt-1">{{ number_format($latestSpotifyImport->imported_count) }}</dd></div>
                        <div><dt class="text-zinc-400">Updated</dt><dd class="mt-1">{{ number_format($latestSpotifyImport->updated_count) }}</dd></div>
                    </dl>
                    @if ($latestSpotifyImport->error)
                        <p class="mt-4 rounded-md bg-red-950/60 p-3 text-red-200">{{ $latestSpotifyImport->error }}</p>
                    @endif
                @else
                    <p class="mt-5 text-sm text-zinc-500">No Spotify album imports have been run yet.</p>
                @endif
            </section>

            <section class="grid gap-6 md:grid-cols-2">
                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                    <h2 class="text-xl font-medium">Top artists</h2>
                    <p class="mt-1 text-sm text-zinc-400">Based on imported Last.fm scrobbles.</p>
                    @if ($topArtists->isNotEmpty())
                        <div class="mt-6 space-y-4">
                            @foreach ($topArtists as $artist)
                                <div class="flex items-start justify-between gap-4 border-b border-zinc-800 pb-3 last:border-0 last:pb-0">
                                    <div>
                                        <h3 class="font-medium">{{ $artist['name'] }}</h3>
                                        <p class="mt-1 text-xs text-zinc-500">Last heard {{ $artist['last_listened_at'] }}</p>
                                    </div>
                                    <span class="whitespace-nowrap text-sm text-zinc-400">{{ number_format($artist['scrobbles']) }} plays</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-6 text-sm text-zinc-500">No Last.fm scrobbles have been imported yet.</p>
                    @endif
                </div>

                <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                    <h2 class="text-xl font-medium">Top albums</h2>
                    <p class="mt-1 text-sm text-zinc-400">Based on imported Last.fm scrobbles with album data.</p>
                    @if ($topAlbums->isNotEmpty())
                        <div class="mt-6 space-y-4">
                            @foreach ($topAlbums as $album)
                                <div class="flex items-start justify-between gap-4 border-b border-zinc-800 pb-3 last:border-0 last:pb-0">
                                    <div>
                                        <h3 class="font-medium">{{ $album['name'] }}</h3>
                                        <p class="mt-1 text-sm text-zinc-400">{{ $album['artist'] }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">Last heard {{ $album['last_listened_at'] }}</p>
                                    </div>
                                    <span class="whitespace-nowrap text-sm text-zinc-400">{{ number_format($album['scrobbles']) }} plays</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-6 text-sm text-zinc-500">No albums are available from the imported scrobbles yet.</p>
                    @endif
                </div>
            </section>

            <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-medium">Albums to complete</h2>
                        <p class="mt-1 text-sm text-zinc-400">Saved Spotify albums with tracks not yet heard in Last.fm.</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-zinc-500">{{ number_format($incompleteAlbums->count()) }} shown</span>
                        <form method="POST" action="{{ route('recommendations.generate') }}">
                            @csrf
                            <button class="rounded-md bg-amber-400 px-3 py-2 text-sm font-medium text-zinc-950 hover:bg-amber-300" type="submit">Generate draft</button>
                        </form>
                    </div>
                </div>

                @if ($incompleteAlbums->isNotEmpty())
                    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        @foreach ($incompleteAlbums as $album)
                            <article class="overflow-hidden rounded-lg border border-zinc-800 bg-zinc-950">
                                @if ($album['cover_image_url'])
                                    <img class="aspect-square w-full object-cover" src="{{ $album['cover_image_url'] }}" alt="Cover art for {{ $album['name'] }}">
                                @else
                                    <div class="flex aspect-square items-center justify-center bg-zinc-900 text-sm text-zinc-600">No artwork</div>
                                @endif
                                <div class="space-y-2 p-4">
                                    <h3 class="font-medium">{{ $album['name'] }}</h3>
                                    <p class="text-sm text-zinc-400">{{ $album['artist'] }}</p>
                                    <p class="text-sm text-zinc-300">{{ $album['heard'] }} / {{ $album['total'] }} tracks heard</p>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-zinc-800" role="progressbar" aria-label="{{ $album['name'] }} completion" aria-valuenow="{{ $album['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                                        <div class="h-full rounded-full bg-amber-400" style="width: {{ $album['percentage'] }}%"></div>
                                    </div>
                                    @if ($album['spotify_url'])
                                        <a class="inline-block text-sm text-[#e56cb0] hover:text-[#f08cc4]" href="{{ $album['spotify_url'] }}" target="_blank" rel="noreferrer">Open in Spotify</a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="mt-6 text-sm text-zinc-500">No incomplete albums are available yet.</p>
                @endif
            </section>

            <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-medium">Latest recommendation draft</h2>
                        <p class="mt-1 text-sm text-zinc-400">Generated from incomplete albums for your review.</p>
                    </div>
                    @if ($latestRecommendation)
                        <span class="text-sm text-zinc-500">{{ $latestRecommendation->generated_at?->format('Y-m-d H:i') }}</span>
                    @endif
                </div>

                @if ($latestRecommendation?->albumRecommendations->isNotEmpty())
                    @php($draftAlbumIds = $latestRecommendation->albumRecommendations->pluck('spotify_saved_album_id')->all())
                    @php($availableSavedAlbums = $spotifySavedAlbums->whereNotIn('id', $draftAlbumIds))
                    @if ($availableSavedAlbums->isNotEmpty())
                        <form class="mt-6 rounded-lg border border-zinc-800 bg-zinc-950 p-4" method="POST" action="{{ route('recommendations.albums.add', $latestRecommendation) }}">
                            @csrf
                            <label class="block text-sm text-zinc-300" for="album_ids">Add saved Spotify albums</label>
                            <select class="mt-2 block w-full rounded-md border-zinc-700 bg-zinc-900 text-zinc-100" id="album_ids" name="album_ids[]" multiple size="6" required>
                                @foreach ($availableSavedAlbums as $album)
                                    <option value="{{ $album->id }}">{{ $album->primary_artist_name ?? 'Unknown artist' }} — {{ $album->name }}</option>
                                @endforeach
                            </select>
                            <button class="mt-4 rounded-md border border-[#BF3989] px-4 py-2 text-sm font-medium text-[#f08cc4] hover:bg-[#BF3989] hover:text-white" type="submit">Add to draft</button>
                        </form>
                    @endif
                    <form class="mt-6" method="POST" action="{{ route('recommendations.review', $latestRecommendation) }}">
                        @csrf
                        <ol class="space-y-3">
                        @foreach ($latestRecommendation->albumRecommendations as $recommendation)
                            <li class="flex items-center justify-between gap-4 rounded-lg border border-zinc-800 bg-zinc-950 p-4">
                                <div class="flex items-center gap-4">
                                    <label class="flex items-center gap-2 text-sm text-zinc-400">
                                        <span class="sr-only">Include {{ $recommendation->spotifySavedAlbum->name }}</span>
                                        <input type="checkbox" name="included[]" value="{{ $recommendation->id }}" @checked($recommendation->included)>
                                        Include
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-zinc-400">
                                        <span class="sr-only">Position for {{ $recommendation->spotifySavedAlbum->name }}</span>
                                        <select class="rounded-md border-zinc-700 bg-zinc-900 text-zinc-100" name="positions[{{ $recommendation->id }}]">
                                            @foreach ($latestRecommendation->albumRecommendations as $positionOption)
                                                <option value="{{ $positionOption->position }}" @selected($recommendation->position === $positionOption->position)>{{ $positionOption->position }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <div>
                                        <h3 class="font-medium">{{ $recommendation->spotifySavedAlbum->name }}</h3>
                                        <p class="mt-1 text-sm text-zinc-400">{{ $recommendation->spotifySavedAlbum->primary_artist_name }} · {{ $recommendation->heard_tracks }} / {{ $recommendation->total_tracks }} tracks heard</p>
                                    </div>
                                    <button class="text-sm text-red-400 hover:text-red-300" type="submit" form="remove-recommendation-{{ $recommendation->id }}">Remove</button>
                                </div>
                                @if ($recommendation->spotifySavedAlbum->spotify_url)
                                    <a class="text-sm text-[#e56cb0] hover:text-[#f08cc4]" href="{{ $recommendation->spotifySavedAlbum->spotify_url }}" target="_blank" rel="noreferrer">Spotify</a>
                                @endif
                            </li>
                        @endforeach
                        </ol>
                        <button class="mt-6 rounded-md bg-amber-400 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-amber-300" type="submit">Save review</button>
                    </form>
                    @foreach ($latestRecommendation->albumRecommendations as $recommendation)
                        <form id="remove-recommendation-{{ $recommendation->id }}" method="POST" action="{{ route('recommendations.albums.remove', [$latestRecommendation, $recommendation]) }}">
                            @csrf
                        </form>
                    @endforeach
                    @if ($latestRecommendation->spotify_playlist_url)
                        <p class="mt-4 text-sm text-emerald-400">Synced {{ $latestRecommendation->synced_at?->format('Y-m-d H:i') }} · <a class="underline" href="{{ $latestRecommendation->spotify_playlist_url }}" target="_blank" rel="noreferrer">Open Spotify playlist</a></p>
                    @elseif ($latestRecommendation->status === 'sync_failed')
                        <p class="mt-4 rounded-md bg-red-950/60 p-3 text-sm text-red-200">Spotify sync failed: {{ $latestRecommendation->sync_error }}</p>
                    @else
                        <form class="mt-4" method="POST" action="{{ route('recommendations.sync', $latestRecommendation) }}">
                            @csrf
                            <button class="rounded-md bg-[#BF3989] px-4 py-2 text-sm font-medium text-white hover:bg-[#a93278]" type="submit">Sync included albums to Spotify</button>
                        </form>
                    @endif
                @else
                    <p class="mt-6 text-sm text-zinc-500">No recommendation draft has been generated yet.</p>
                @endif
            </section>

            <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-medium">Imported Spotify albums</h2>
                        <p class="mt-1 text-sm text-zinc-400">Saved albums are shown newest first for review.</p>
                    </div>
                    <span class="text-sm text-zinc-500">{{ number_format($spotifySavedAlbums->count()) }} albums</span>
                </div>

                @if ($spotifySavedAlbums->isNotEmpty())
                    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($spotifySavedAlbums as $album)
                            <article class="overflow-hidden rounded-lg border border-zinc-800 bg-zinc-950">
                                @if ($album->cover_image_url)
                                    <img class="aspect-square w-full object-cover" src="{{ $album->cover_image_url }}" alt="Cover art for {{ $album->name }}">
                                @else
                                    <div class="flex aspect-square items-center justify-center bg-zinc-900 text-sm text-zinc-600">No artwork</div>
                                @endif
                                <div class="space-y-2 p-4">
                                    <h3 class="font-medium">{{ $album->name }}</h3>
                                    <p class="text-sm text-zinc-400">{{ $album->primary_artist_name ?? 'Unknown artist' }}</p>
                                    <p class="text-xs text-zinc-500">{{ $album->release_date ?: 'Release date unknown' }}</p>
                                    @if ($album->spotify_url)
                                        <a class="inline-block text-sm text-[#e56cb0] hover:text-[#f08cc4]" href="{{ $album->spotify_url }}" target="_blank" rel="noreferrer">Open in Spotify</a>
                                    @endif
                                    @if ($album->completion_override === null)
                                        <div class="flex flex-wrap gap-2 pt-2">
                                            <form method="POST" action="{{ route('albums.completion.complete', $album) }}">
                                                @csrf
                                                <button class="text-xs text-emerald-400 hover:text-emerald-300" type="submit">Mark heard</button>
                                            </form>
                                            <form method="POST" action="{{ route('albums.completion.incomplete', $album) }}">
                                                @csrf
                                                <button class="text-xs text-amber-400 hover:text-amber-300" type="submit">Mark incomplete</button>
                                            </form>
                                        </div>
                                    @else
                                        <p class="pt-2 text-xs text-zinc-500">Manual: {{ $album->completion_override ? 'heard' : 'incomplete' }}</p>
                                        <form method="POST" action="{{ route('albums.completion.clear', $album) }}">
                                            @csrf
                                            <button class="text-xs text-zinc-400 hover:text-zinc-200" type="submit">Clear override</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <p class="mt-6 text-sm text-zinc-500">No saved Spotify albums have been imported yet.</p>
                @endif
            </section>

            <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-medium">Recent imports</h2>
                        <p class="mt-1 text-sm text-zinc-400">Every run is retained so retries remain visible and auditable.</p>
                    </div>
                </div>
                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-zinc-800 text-zinc-400">
                            <tr>
                                <th class="px-3 py-3 font-normal">Started</th>
                                <th class="px-3 py-3 font-normal">Range</th>
                                <th class="px-3 py-3 font-normal">Mode</th>
                                <th class="px-3 py-3 font-normal">Status</th>
                                <th class="px-3 py-3 font-normal">Results</th>
                                <th class="px-3 py-3 font-normal"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentImports as $import)
                                <tr class="border-b border-zinc-800/70">
                                    <td class="px-3 py-3 text-zinc-300">{{ $import->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="px-3 py-3 text-zinc-400">{{ $import->from_at->toDateString() }} – {{ $import->to_at->toDateString() }}</td>
                                    <td class="px-3 py-3 text-zinc-400">{{ $import->requested_limit ? 'Bounded' : 'Full' }}</td>
                                    <td class="px-3 py-3 capitalize">{{ $import->status }}</td>
                                    <td class="px-3 py-3 text-zinc-400">{{ number_format($import->imported_count) }} imported / {{ number_format($import->skipped_count) }} skipped</td>
                                    <td class="px-3 py-3 text-right">
                                        @if ($import->status === 'failed')
                                            <form method="POST" action="{{ route('imports.lastfm.retry', $import) }}">
                                                @csrf
                                                <button class="text-emerald-400 hover:text-emerald-300" type="submit">Retry</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="px-3 py-6 text-zinc-500" colspan="6">No import history yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </body>
</html>
