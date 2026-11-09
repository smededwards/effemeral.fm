<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Catalogue · Effemeral</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100">
        <main class="mx-auto max-w-5xl space-y-8 px-6 py-12">
            <header>
                <a class="text-sm text-emerald-400 hover:text-emerald-300" href="{{ route('dashboard') }}">← Dashboard</a>
                <p class="mt-8 text-sm uppercase tracking-[0.3em] text-emerald-400">Effemeral</p>
                <h1 class="mt-3 text-4xl font-semibold">Catalogue</h1>
                <p class="mt-3 max-w-2xl text-zinc-400">Choose a canonical artist or album to use as the starting point for discovery.</p>
            </header>

            <form class="rounded-xl border border-zinc-800 bg-zinc-900 p-6" method="GET" action="{{ route('catalogue.index') }}">
                <label class="block text-sm font-medium text-zinc-200" for="catalogue-search">Search artists and albums</label>
                <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                    <input id="catalogue-search" class="block w-full rounded-md border-zinc-700 bg-zinc-950 text-zinc-100" type="search" name="q" value="{{ $search }}" placeholder="Cult of Luna, Mariner..." autofocus>
                    <button class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400" type="submit">Search</button>
                </div>
            </form>

            @if ($selectedArtist)
                <section class="rounded-xl border border-emerald-900 bg-emerald-950/30 p-6">
                    <p class="text-xs uppercase tracking-[0.2em] text-emerald-400">Selected artist</p>
                    <h2 class="mt-2 text-2xl font-semibold">{{ $selectedArtist->name }}</h2>
                    <p class="mt-2 text-sm text-zinc-400">{{ $selectedArtist->albums->count() }} canonical albums</p>
                    @if ($selectedArtist->albums->isNotEmpty())
                        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($selectedArtist->albums as $album)
                                <a class="rounded-lg border border-zinc-800 bg-zinc-950 p-4 hover:border-emerald-700" href="{{ route('catalogue.index', ['album' => $album->id]) }}">
                                    <h3 class="font-medium">{{ $album->title }}</h3>
                                    <p class="mt-1 text-sm text-zinc-500">{{ $album->tracks->count() }} tracks</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-5 text-sm text-zinc-500">No canonical albums are linked to this artist yet.</p>
                    @endif
                </section>
            @endif

            @if ($selectedAlbum)
                <section class="rounded-xl border border-emerald-900 bg-emerald-950/30 p-6">
                    <p class="text-xs uppercase tracking-[0.2em] text-emerald-400">Selected album</p>
                    <h2 class="mt-2 text-2xl font-semibold">{{ $selectedAlbum->title }}</h2>
                    <p class="mt-2 text-zinc-300">{{ $selectedAlbum->artists->pluck('name')->implode(', ') ?: 'Unknown artist' }}</p>
                    <p class="mt-2 text-sm text-zinc-500">{{ $selectedAlbum->tracks->count() }} canonical tracks{{ $selectedAlbum->release_date ? ' · '.$selectedAlbum->release_date : '' }}</p>
                    <p class="mt-5 text-sm text-zinc-400">This album is ready to become the context for genre, year, and completion-aware discovery.</p>
                </section>
            @endif

            @if ($search !== '')
                <div class="grid gap-6 md:grid-cols-2">
                    <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                        <h2 class="text-xl font-medium">Artists</h2>
                        @if ($artists->isNotEmpty())
                            <div class="mt-5 space-y-3">
                                @foreach ($artists as $artist)
                                    <a class="block rounded-lg border border-zinc-800 bg-zinc-950 p-4 hover:border-emerald-700" href="{{ route('catalogue.index', ['q' => $search, 'artist' => $artist->id]) }}">
                                        <h3 class="font-medium">{{ $artist->name }}</h3>
                                        <p class="mt-1 text-sm text-zinc-500">{{ $artist->albums()->count() }} canonical albums</p>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-5 text-sm text-zinc-500">No matching artists.</p>
                        @endif
                    </section>

                    <section class="rounded-xl border border-zinc-800 bg-zinc-900 p-6">
                        <h2 class="text-xl font-medium">Albums</h2>
                        @if ($albums->isNotEmpty())
                            <div class="mt-5 space-y-3">
                                @foreach ($albums as $album)
                                    <a class="block rounded-lg border border-zinc-800 bg-zinc-950 p-4 hover:border-emerald-700" href="{{ route('catalogue.index', ['q' => $search, 'album' => $album->id]) }}">
                                        <h3 class="font-medium">{{ $album->title }}</h3>
                                        <p class="mt-1 text-sm text-zinc-400">{{ $album->artists->pluck('name')->implode(', ') ?: 'Unknown artist' }}</p>
                                        <p class="mt-1 text-sm text-zinc-500">{{ $album->tracks()->count() }} canonical tracks</p>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-5 text-sm text-zinc-500">No matching albums.</p>
                        @endif
                    </section>
                </div>
            @else
                <p class="rounded-xl border border-dashed border-zinc-800 p-8 text-center text-sm text-zinc-500">Search the catalogue to choose an artist or album.</p>
            @endif
        </main>
    </body>
</html>
