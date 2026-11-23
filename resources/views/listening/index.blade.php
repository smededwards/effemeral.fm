@extends('layouts.app', ['title' => 'Listening Archive · Effemeral'])

@section('content')
    <header class="flex flex-col gap-4 border-b border-ef-line pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold tracking-[0.24em] text-ef-accent-strong">Listening / 06</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Listening Archive</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-ef-muted">Explore your Last.fm history by artist, album, or
                track, across any listening period.</p>
        </div>
        <a class="text-sm font-semibold text-ef-accent-strong hover:underline"
            href="{{ route('recommendations.index', [], false) }}">Albums to Complete →</a>
    </header>

    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]" aria-labelledby="listening-results-heading">
        <div class="min-w-0 border border-ef-line bg-ef-surface p-5 sm:p-8">
            <nav aria-label="Listening Views">
                <ul class="flex flex-wrap gap-2 border-b border-ef-line">
                    @foreach ($types as $viewType => $label)
                        <li>
                            <a class="block border-b-2 px-4 py-3 text-sm font-semibold transition {{ $type === $viewType ? 'border-ef-accent text-ef-text' : 'border-transparent text-ef-muted hover:border-ef-accent hover:text-ef-text' }}"
                                href="{{ route('listening.index', ['type' => $viewType, 'period' => $period], false) }}"
                                @if ($type === $viewType) aria-current="page" @endif>{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <nav class="mt-5" aria-label="Time Period">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($periods as $periodKey => $label)
                        <li>
                            <a class="block min-h-11 border px-3 py-2 text-sm transition {{ $period === $periodKey ? 'border-ef-accent bg-ef-accent text-ef-accent-ink' : 'border-ef-line text-ef-muted hover:border-ef-accent hover:text-ef-text' }}"
                                href="{{ route('listening.index', ['type' => $type, 'period' => $periodKey], false) }}"
                                @if ($period === $periodKey) aria-current="page" @endif>{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="mt-6 flex flex-wrap items-baseline justify-between gap-2 border-b border-ef-line pb-4">
                <h2 id="listening-results-heading" class="text-2xl font-semibold">{{ $types[$type] }} ·
                    {{ $periods[$period] }}</h2>
                <p class="text-sm tabular-nums text-ef-muted">{{ number_format($scrobbleCount) }} scrobbles</p>
            </div>

            <ol class="divide-y divide-ef-line" aria-label="{{ $types[$type] }} ranked by scrobbles">
                @forelse ($results as $result)
                    <li class="flex min-w-0 items-center gap-4 py-4 sm:gap-5">
                        <span class="w-8 shrink-0 text-center text-sm font-semibold tabular-nums text-ef-accent-strong"
                            aria-hidden="true">{{ str_pad((string) ($results->firstItem() + $loop->index), 2, '0', STR_PAD_LEFT) }}</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-semibold">{{ $result['name'] }}</h3>
                            @if ($result['artist'] && $type !== 'artists')
                                <p class="mt-1 truncate text-sm text-ef-muted">{{ $result['artist'] }}</p>
                            @endif
                            <p class="mt-1 text-xs text-ef-muted">Last heard {{ $result['last_heard'] }}</p>
                        </div>
                        <span
                            class="shrink-0 text-sm tabular-nums text-ef-muted">{{ number_format($result['scrobbles']) . ' ' . ($result['scrobbles'] === 1 ? 'play' : 'plays') }}</span>
                    </li>
                @empty
                    <li class="mt-4 border border-dashed border-ef-line p-6 text-sm text-ef-muted">
                        {{ 'No Last.fm listening history is available for this range yet.' }}</li>
                @endforelse
            </ol>

            @if ($results->hasPages())
                <div class="mt-6 border-t border-ef-line pt-5">{{ $results->links() }}</div>
            @endif
        </div>

        <aside class="border border-ef-line bg-ef-canvas p-5 sm:p-6">
            <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong">Your Data</p>
            <h2 class="mt-2 text-xl font-semibold">From Last.fm</h2>
            <p class="mt-3 text-sm leading-6 text-ef-muted">These rankings use imported scrobbles. Spotify library data does
                not affect play counts or listening dates.</p>
            <a class="mt-5 inline-flex min-h-11 items-center text-sm font-semibold text-ef-accent-strong hover:underline"
                href="{{ route('imports.index', [], false) }}">Manage Imports →</a>
        </aside>
    </section>
@endsection
