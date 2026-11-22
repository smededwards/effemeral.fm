@extends('layouts.app', ['title' => 'Audit playlist coverage · Effemeral'])

@section('content')
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm tracking-[0.3em] text-ef-accent-strong">Effemeral</p>
                    <h1 class="mt-3 text-4xl font-semibold">Playlist coverage audit</h1>
                    <p class="mt-3 max-w-2xl text-ef-muted">Compare a recommendation draft with every imported Spotify playlist or one selected destination.</p>
                </div>
                <a class="text-sm text-ef-accent-strong hover:text-ef-accent-soft" href="{{ route('dashboard', [], false) }}">Back to dashboard</a>
            </header>

            <form class="rounded-xl border border-ef-line bg-ef-surface p-6" method="GET" action="{{ route('recommendations.audit', [], false) }}">
                <label class="block text-sm text-ef-muted" for="run">Recommendation draft</label>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <select class="min-w-0 flex-1 rounded-md border-ef-line bg-ef-canvas text-ef-text" id="run" name="run" required>
                        <option value="">Select a saved draft</option>
                        @foreach ($recommendationRuns as $run)
                            <option value="{{ $run->id }}" @selected($selectedRun?->id === $run->id)>{{ $run->generated_at?->format('Y-m-d H:i') }} · {{ $run->source }} · {{ $run->status }}</option>
                        @endforeach
                    </select>
                    <label class="block text-sm text-ef-muted" for="playlist">Playlist
                        <select class="mt-2 min-w-0 w-full flex-1 rounded-md border-ef-line bg-ef-canvas text-ef-text" id="playlist" name="playlist">
                        <option value="">All imported playlists</option>
                        @foreach ($playlists as $playlist)
                            <option value="{{ $playlist->id }}" @selected($selectedPlaylist?->id === $playlist->id)>{{ $playlist->name }}</option>
                        @endforeach
                        </select>
                    </label>
                    <button class="rounded-md bg-ef-accent px-4 py-2 text-sm font-medium text-ef-accent-ink hover:bg-ef-accent-soft" type="submit">Run audit</button>
                </div>
            </form>

            @if ($selectedRun)
                <section class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-ef-line bg-ef-surface p-5">
                        <p class="text-sm text-ef-muted">Complete albums</p>
                        <p class="mt-2 text-3xl font-semibold text-ef-accent-strong">{{ $completeAlbums }}</p>
                    </div>
                    <div class="rounded-xl border border-ef-line bg-ef-surface p-5">
                        <p class="text-sm text-ef-muted">Partial albums</p>
                        <p class="mt-2 text-3xl font-semibold text-ef-accent-strong">{{ $partialAlbums }}</p>
                    </div>
                    <div class="rounded-xl border border-ef-line bg-ef-surface p-5">
                        <p class="text-sm text-ef-muted">Missing albums</p>
                        <p class="mt-2 text-3xl font-semibold text-red-300">{{ $missingAlbums }}</p>
                    </div>
                </section>

                <section class="rounded-xl border border-ef-line bg-ef-surface p-6">
                    <h2 class="text-xl font-medium">Album coverage</h2>
                    <div class="mt-6 space-y-3">
                        @forelse ($auditAlbums as $item)
                            @php($recommendation = $item['recommendation'])
                            <article class="rounded-lg border border-ef-line bg-ef-canvas p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-medium">{{ $recommendation->spotifySavedAlbum->name }}</h3>
                                        <p class="mt-1 text-sm text-ef-muted">{{ $recommendation->spotifySavedAlbum->primary_artist_name ?? 'Unknown artist' }}</p>
                                    </div>
                                    <span class="rounded-full px-3 py-1 text-xs {{ $item['status'] === 'complete' ? 'bg-ef-accent-wash text-ef-accent-strong' : ($item['status'] === 'partial' ? 'bg-amber-950 text-ef-accent-strong' : 'bg-red-950 text-red-300') }}">{{ ucfirst($item['status']) }}</span>
                                </div>
                                <p class="mt-3 text-sm text-ef-muted">{{ $item['covered_tracks'] }} / {{ $item['expected_tracks'] }} tracks found in imported playlists</p>
                                @if ($item['playlists']->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach ($item['playlists'] as $playlist)
                                            @if ($playlist['url'])
                                                <a class="rounded bg-ef-surface-raised px-2 py-1 text-xs text-ef-accent-soft hover:text-ef-accent-strong" href="{{ $playlist['url'] }}" target="_blank" rel="noreferrer">{{ $playlist['name'] }} · {{ $playlist['tracks'] }} tracks</a>
                                            @else
                                                <span class="rounded bg-ef-surface-raised px-2 py-1 text-xs text-ef-muted">{{ $playlist['name'] }} · {{ $playlist['tracks'] }} tracks</span>
                                            @endif
                                        @endforeach
                                    </div>
                                @elseif ($item['status'] === 'missing')
                                    <p class="mt-3 text-xs text-ef-muted">No matching tracks found in the imported playlists.</p>
                                @endif
                            </article>
                        @empty
                            <p class="text-sm text-ef-muted">This draft has no albums to audit.</p>
                        @endforelse
                    </div>
                </section>
            @else
                <p class="rounded-xl border border-ef-line bg-ef-surface p-6 text-sm text-ef-muted">Select a saved recommendation draft to begin.</p>
            @endif
@endsection
