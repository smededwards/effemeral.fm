<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Effemeral' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#0b0c0a] text-[#f3f5f3] antialiased">
        <div class="min-h-screen border-t-4 border-[#00e676]">
            <main class="mx-auto max-w-7xl px-5 py-6 sm:px-8 lg:px-12 lg:py-10">
                <header class="border-b border-[#2e382f] pb-5">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                        <a class="flex items-center gap-3" href="{{ route('dashboard') }}">
                            <span class="flex h-10 w-10 items-center justify-center bg-[#00e676] text-sm font-bold tracking-tight text-white">EF</span>
                            <span>
                                <span class="block text-sm font-semibold tracking-[0.22em] text-[#00e676]">EFFEMERAL</span>
                                <span class="mt-1 block text-xs text-[#9aa39d]">Listening archive</span>
                            </span>
                        </a>
                        <p class="text-xs tabular-nums text-[#9aa39d]">{{ now()->format('Y.m.d') }}</p>
                    </div>
                    <nav class="mt-6 -mb-5" aria-label="Primary navigation">
                        <div class="flex flex-wrap gap-1">
                            @foreach ([
                                ['route' => 'dashboard', 'label' => 'Overview'],
                                ['route' => 'imports.index', 'label' => 'Imports'],
                                ['route' => 'library.index', 'label' => 'Library'],
                                ['route' => 'catalogue.index', 'label' => 'Catalogue'],
                                ['route' => 'recommendations.index', 'label' => 'Drafts'],
                            ] as $item)
                                <a class="border-b-2 px-3 py-3 text-sm font-medium transition {{ request()->routeIs($item['route']) ? 'border-[#00e676] bg-[#00e676] text-white' : 'border-transparent text-[#9aa39d] hover:border-[#00e676] hover:text-[#00e676]' }}" href="{{ route($item['route']) }}" @if (request()->routeIs($item['route'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    </nav>
                </header>

                @if (session('success'))
                    <div class="mt-6 border-l-4 border-[#00e676] bg-[#111612] px-4 py-3 text-sm text-[#b7ffd1]" role="status">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="mt-6 border-l-4 border-[#b42318] bg-[#111612] px-4 py-3 text-sm text-[#8d1c14]" role="alert">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mt-6 border-l-4 border-[#b42318] bg-[#111612] px-4 py-3 text-sm text-[#8d1c14]" role="alert">
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
