@extends('layouts.app', ['title' => 'Styles · Catalogue · Effemeral'])

@section('content')
    <header class="border-b border-ef-line pb-8">
        <a class="text-sm font-semibold text-ef-accent-strong hover:underline"
            href="{{ route('catalogue.index', [], false) }}">← Catalogue</a>
        <p class="mt-5 text-xs font-semibold tracking-[0.24em] text-ef-accent-strong uppercase">Catalogue / Styles</p>
        <h1 class="mt-3 text-4xl font-semibold">Styles</h1>
        <p class="mt-3 max-w-2xl text-ef-muted">Browse the accepted styles represented by artists in your catalogue.</p>
    </header>

    <section class="rounded-xl border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="catalogue-styles-heading">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-ef-line pb-4">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Accepted catalogue data</p>
                <h2 id="catalogue-styles-heading" class="mt-2 text-2xl font-semibold">All styles</h2>
            </div>
            <span class="text-sm text-ef-muted">{{ number_format($genres->count()) }} styles</span>
        </div>
        @if ($genres->isNotEmpty())
            <ul class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($genres as $genre)
                    <li>
                        <a class="flex items-center justify-between border border-ef-line bg-ef-canvas p-5 font-semibold transition hover:border-ef-accent hover:text-ef-accent-strong"
                            href="{{ route('genres.show', $genre, false) }}">
                            <span>{{ $genre->name }}</span>
                            <span class="text-sm font-normal text-ef-muted">{{ $genre->artists_count }} artists</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-5 text-sm text-ef-muted">No accepted styles have been added to the catalogue yet.</p>
        @endif
    </section>
@endsection
