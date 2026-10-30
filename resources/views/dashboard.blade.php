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
