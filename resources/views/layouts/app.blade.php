<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Effemeral' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100">
        <main class="mx-auto max-w-5xl space-y-8 px-6 py-12">
            <nav class="flex flex-wrap gap-4 border-b border-zinc-800 pb-4 text-sm">
                <a class="text-emerald-400 hover:text-emerald-300" href="{{ route('dashboard') }}">Overview</a>
                <a class="text-emerald-400 hover:text-emerald-300" href="{{ route('imports.index') }}">Imports</a>
                <a class="text-emerald-400 hover:text-emerald-300" href="{{ route('library.index') }}">Library</a>
                <a class="text-emerald-400 hover:text-emerald-300" href="{{ route('catalogue.index') }}">Catalogue</a>
                <a class="text-emerald-400 hover:text-emerald-300" href="{{ route('recommendations.index') }}">Recommendations</a>
            </nav>

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

            @yield('content')
        </main>
    </body>
</html>
