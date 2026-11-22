<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Effemeral' }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700&family=Noto+Serif:wght@400;500;600;700&display=swap" rel="stylesheet">
        @php
            $vite = app(\Illuminate\Foundation\Vite::class);
            $stylesheet = parse_url($vite->asset('resources/css/app.css'), PHP_URL_PATH);
            $script = parse_url($vite->asset('resources/js/app.js'), PHP_URL_PATH);
        @endphp
        <link rel="preload" as="style" href="{{ $stylesheet }}" />
        <link rel="modulepreload" as="script" href="{{ $script }}" />
        <link rel="stylesheet" href="{{ $stylesheet }}" />
        <script type="module" src="{{ $script }}"></script>
    </head>
    <body class="min-h-screen bg-ef-canvas text-ef-text antialiased">
        <div class="min-h-screen border-t-4 border-ef-accent">
            <a class="sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:not-sr-only focus:bg-ef-accent focus:px-4 focus:py-3 focus:text-ef-accent-ink" href="#main-content">Skip to main content</a>
            <header class="mx-auto max-w-7xl px-5 pt-6 sm:px-8 lg:px-12 lg:pt-10">
                <div class="border-b border-ef-line pb-5">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <a class="flex items-center gap-3" href="{{ route('dashboard', [], false) }}">
                            <span class="flex h-10 w-10 items-center justify-center bg-ef-accent text-sm font-bold tracking-tight text-ef-accent-ink">EF</span>
                            <span>
                                <span class="block text-sm font-semibold tracking-[0.08em] text-ef-accent-strong">Effemeral</span>
                                <span class="mt-1 block text-xs text-ef-muted">Listening archive</span>
                            </span>
                        </a>
                        <p class="text-xs tabular-nums text-ef-muted">{{ now()->format('Y.m.d') }}</p>
                    </div>
                    <nav class="mt-6 -mb-5" aria-label="Primary navigation">
                        <div class="flex flex-wrap gap-1">
                            @foreach ([
                                ['route' => 'dashboard', 'label' => 'Overview'],
                                ['route' => 'recommendations.index', 'label' => 'To complete'],
                                ['route' => 'playlists.index', 'label' => 'Playlists'],
                            ] as $item)
                                <a class="border-b-2 px-3 py-3 text-sm font-medium transition {{ request()->routeIs($item['route']) ? 'border-ef-accent bg-ef-accent text-ef-accent-ink' : 'border-transparent text-ef-muted hover:border-ef-accent hover:text-ef-accent-strong' }}" href="{{ route($item['route'], [], false) }}" @if (request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-center gap-2 border-t border-ef-line pt-2">
                            <span class="inline-flex min-h-11 items-center px-2 text-xs tracking-[0.16em] text-ef-muted">Data</span>
                            @foreach ([
                                ['route' => 'imports.index', 'label' => 'Imports'],
                                ['route' => 'library.index', 'label' => 'Library'],
                                ['route' => 'catalogue.index', 'label' => 'Catalogue'],
                            ] as $item)
                                <a class="inline-flex min-h-11 items-center px-2 text-xs font-medium text-ef-muted transition hover:text-ef-accent-strong" href="{{ route($item['route'], [], false) }}" @if (request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </nav>
                </div>
            </header>

            <main id="main-content" class="page-enter mx-auto max-w-7xl px-5 py-6 sm:px-8 lg:px-12 lg:py-10" tabindex="-1">
                @if (session('success'))
                    <div class="mt-6 border-l-4 border-ef-accent bg-ef-surface px-4 py-3 text-sm text-ef-accent-soft" role="status">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="mt-6 border-l-4 border-red-800 bg-ef-surface px-4 py-3 text-sm text-red-200" role="alert">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mt-6 border-l-4 border-red-800 bg-ef-surface px-4 py-3 text-sm text-red-200" role="alert">
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-10 pt-10">
                    @yield('content')
                </div>
            </main>
        </div>
    </body>
</html>
