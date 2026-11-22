<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Effemeral' }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
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
            <a class="sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:not-sr-only focus:bg-ef-accent focus:px-4 focus:py-3 focus:text-ef-accent-ink" href="#main-content">Skip to Main Content</a>
            <header class="mx-auto max-w-7xl px-5 pt-5 sm:px-8 lg:px-12 lg:pt-8">
                <div class="flex flex-col gap-5 border-b border-ef-line pb-5 lg:flex-row lg:items-center lg:justify-between lg:gap-8">
                    <a class="flex w-fit shrink-0 items-center gap-3" href="{{ route('dashboard', [], false) }}">
                        <span class="inline-flex h-12 w-12 items-center justify-center border border-ef-accent-line bg-ef-surface font-serif text-4xl leading-none text-ef-accent-strong" aria-hidden="true">♪</span>
                        <span>
                            <span class="block text-base font-semibold tracking-[0.08em] text-ef-accent-strong">Effemeral</span>
                            <span class="mt-1 block text-sm text-ef-muted">Listening archive</span>
                        </span>
                    </a>
                    <nav class="w-full lg:w-auto" aria-label="Main Navigation">
                        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:flex lg:flex-wrap lg:justify-end lg:gap-1">
                            @foreach ([
                                ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'Overview'],
                                ['route' => 'playlists.index', 'active' => ['playlists.*', 'album-playlists.*', 'spotify-playlists.*'], 'label' => 'Playlists'],
                                ['route' => 'catalogue.index', 'active' => ['catalogue.*', 'artists.show', 'albums.show'], 'label' => 'Catalogue'],
                                ['route' => 'recommendations.index', 'active' => ['recommendations.*'], 'label' => 'To Complete'],
                                ['route' => 'library.index', 'active' => ['library.*'], 'label' => 'Library'],
                                ['route' => 'listening.index', 'active' => ['listening.*'], 'label' => 'Listening'],
                                ['route' => 'imports.index', 'active' => ['imports.*'], 'label' => 'Imports'],
                            ] as $item)
                                @php($isActive = request()->routeIs(...$item['active']))
                                <li>
                                    <a class="flex min-h-11 items-center border-l-2 px-4 py-2 text-sm font-semibold transition-colors lg:justify-center lg:border-l-0 lg:border-b-2 lg:px-3 {{ $isActive ? 'border-ef-accent bg-ef-accent text-ef-accent-ink lg:bg-ef-accent-wash lg:text-ef-text' : 'border-ef-line bg-ef-surface text-ef-muted hover:border-ef-accent hover:text-ef-text lg:border-transparent lg:bg-transparent lg:hover:bg-ef-surface' }}" href="{{ route($item['route'], [], false) }}" @if ($isActive) aria-current="page" @endif>{{ $item['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
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
