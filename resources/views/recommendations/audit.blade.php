<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Audit playlist coverage · Effemeral</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100">
        <main class="mx-auto max-w-5xl space-y-8 px-6 py-12">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm uppercase tracking-[0.3em] text-emerald-400">Effemeral</p>
                    <h1 class="mt-3 text-4xl font-semibold">Playlist coverage audit</h1>
                    <p class="mt-3 max-w-2xl text-zinc-400">Compare any recommendation draft with the imported Spotify playlists.</p>
                </div>
                <a class="text-sm text-[#e56cb0] hover:text-[#f08cc4]" href="{{ route('dashboard') }}">Back to dashboard</a>
            </header>

            <form class="rounded-xl border border-zinc-800 bg-zinc-900 p-6" method="GET" action="{{ route('recommendations.audit') }}">
                <label class="block text-sm text-zinc-300" for="run">Recommendation draft</label>
                <div class="mt-3 flex flex-wrap gap-3">
                    <select class="min-w-0 flex-1 rounded-md border-zinc-700 bg-zinc-950 text-zinc-100" id="run" name="run" required>
                        <option value="">Select a saved draft</option>
                        @foreach ($recommendationRuns as $run)
                            <option value="{{ $run->id }}" @selected($selectedRun?->id === $run->id)>{{ $run->generated_at?->format('Y-m-d H:i') }} · {{ $run->source }} · {{ $run->status }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-md bg-amber-400 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-amber-300" type="submit">Run audit</button>
                </div>
            </form>

            @if ($selectedRun)
                <section class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                        <p class="text-sm text-zinc-500">Complete albums</p>
                        <p class="mt-2 text-3xl font-semibold text-emerald-300">{{ $completeAlbums }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                        <p class="text-sm text-zinc-500">Partial albums</p>
                        <p class="mt-2 text-3xl font-semibold text-amber-300">{{ $partialAlbums }}</p>
                    </div>
                    <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
                        <p class="text-sm text-zinc-500">Missing albums</p>
                        <p class="mt-2 text-3xl font-semibold text-red-300">{{ $missingAlbums }}</p>
                    </div>
                </section>

                <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                    <h2 class="text-xl font-medium">Album coverage</h2>
                    <div class="mt-6 space-y-3">
                        @forelse ($auditAlbums as $item)
                            @php($recommendation = $item['recommendation'])
                            <article class="rounded-lg border border-zinc-800 bg-zinc-950 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-medium">{{ $recommendation->spotifySavedAlbum->name }}</h3>
                                        <p class="mt-1 text-sm text-zinc-400">{{ $recommendation->spotifySavedAlbum->primary_artist_name ?? 'Unknown artist' }}</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs {{ $item['status'] === 'complete' ? 'bg-emerald-950 text-emerald-300' : ($item['status'] === 'partial' ? 'bg-amber-950 text-amber-300' : 'bg-red-950 text-red-300') }}">{{ ucfirst($item['status']) }}</span>
                                </div>
                                <p class="mt-3 text-sm text-zinc-300">{{ $item['covered_tracks'] }} / {{ $item['expected_tracks'] }} tracks found in imported playlists</p>
                                @if ($item['playlists']->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($item['playlists'] as $playlist)
                                            @if ($playlist['url'])
                                                <a class="rounded bg-zinc-800 px-2 py-1 text-xs text-[#f08cc4] hover:text-white" href="{{ $playlist['url'] }}" target="_blank" rel="noreferrer">{{ $playlist['name'] }} · {{ $playlist['tracks'] }} tracks</a>
                                            @else
                                                <span class="rounded bg-zinc-800 px-2 py-1 text-xs text-zinc-300">{{ $playlist['name'] }} · {{ $playlist['tracks'] }} tracks</span>
                                            @endif
                                        @endforeach
                                    </div>
                                @elseif ($item['status'] === 'missing')
                                    <p class="mt-3 text-xs text-zinc-500">No matching tracks found in the imported playlists.</p>
                                @endif
                            </article>
                        @empty
                            <p class="text-sm text-zinc-500">This draft has no albums to audit.</p>
                        @endforelse
                    </div>
                </section>
            @else
                <p class="rounded-xl border border-zinc-800 bg-zinc-900 p-6 text-sm text-zinc-400">Select a saved recommendation draft to begin.</p>
            @endif
        </main>
    </body>
</html>
