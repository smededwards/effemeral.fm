@extends('layouts.app', ['title' => 'To Complete · Effemeral'])

@section('content')
    <header class="flex flex-col gap-4 border-b border-ef-line pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold tracking-[0.24em] text-ef-accent-strong uppercase">Listening Library</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">To Complete</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-ef-muted">Albums from your Spotify library, matched against
                Last.fm listening history. Review a draft before syncing it to Spotify.</p>
        </div>
        <a class="text-sm font-semibold text-ef-accent-strong hover:underline"
            href="{{ route('recommendations.audit', [], false) }}">Audit Playlists →</a>
    </header>

    <section class="border border-ef-line bg-ef-surface p-6 sm:p-8" aria-labelledby="incomplete-albums-heading">
        <div class="flex flex-col gap-4 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-accent-strong uppercase">Next Listens</p>
                <h2 id="incomplete-albums-heading" class="mt-2 text-2xl font-semibold">Incomplete Albums</h2>
                <p class="mt-2 text-sm text-ef-muted">{{ number_format($incompleteAlbums->count()) }} albums ready to
                    continue.</p>
            </div>
            <form class="flex flex-col gap-3 sm:flex-row sm:items-end" method="POST"
                action="{{ route('recommendations.generate', [], false) }}">
                @csrf
                <label class="text-sm text-ef-muted" for="recommendation-listening-window">Exclude albums heard within
                    <select id="recommendation-listening-window"
                        class="mt-2 block border border-ef-line bg-ef-canvas px-3 py-3 text-ef-text" name="listened_within">
                        @foreach ($recommendationListeningWindows as $value => $label)
                            <option value="{{ $value }}" @selected($value === '1_year')>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="bg-ef-accent px-4 py-3 text-sm font-semibold text-ef-accent-ink hover:bg-ef-accent-hover"
                    type="submit">Generate Draft</button>
            </form>
        </div>

        <ol class="mt-2 divide-y divide-ef-line" aria-label="Incomplete albums">
            @forelse ($incompleteAlbums as $album)
                <li class="flex min-w-0 items-center gap-4 py-4 sm:gap-5">
                    <span class="w-8 shrink-0 text-center text-sm font-semibold tabular-nums text-ef-accent-strong"
                        aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    @if ($album['cover_image_url'])
                        <img class="h-16 w-16 shrink-0 object-cover sm:h-18 sm:w-18" src="{{ $album['cover_image_url'] }}"
                            alt="Cover art for {{ $album['name'] }}">
                    @else
                        <div
                            class="flex h-16 w-16 shrink-0 items-center justify-center bg-ef-surface-raised text-xs text-ef-muted sm:h-18 sm:w-18">
                            No Art</div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate font-semibold">{{ $album['name'] }}</h3>
                        <p class="mt-1 truncate text-sm text-ef-muted">{{ $album['artist'] }}</p>
                        <div class="mt-3 flex items-center gap-3">
                            <div class="h-1.5 min-w-0 flex-1 bg-ef-track" role="progressbar"
                                aria-label="{{ $album['name'] }} completion" aria-valuenow="{{ $album['percentage'] }}"
                                aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full bg-ef-accent" style="width: {{ $album['percentage'] }}%"></div>
                            </div>
                            <span
                                class="shrink-0 text-xs font-medium tabular-nums text-ef-text">{{ $album['heard'] . ' of ' . $album['total'] . ' tracks · ' . $album['percentage'] . '%' }}</span>
                        </div>
                    </div>
                    @if ($album['spotify_url'])
                        <a class="hidden shrink-0 text-sm font-semibold text-ef-accent-strong hover:underline sm:inline"
                            href="{{ $album['spotify_url'] }}" target="_blank" rel="noreferrer">Open in Spotify</a>
                    @endif
                </li>
            @empty
                <li class="mt-4 border border-dashed border-ef-line p-6 text-sm text-ef-muted">No incomplete albums are
                    available yet.</li>
            @endforelse
        </ol>
    </section>

    <section class="border border-ef-line bg-ef-canvas p-6 sm:p-8" aria-labelledby="latest-draft-heading">
        <div class="flex flex-col gap-3 border-b border-ef-line pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-ef-muted">Latest work</p>
                <h2 id="latest-draft-heading" class="mt-2 text-2xl font-semibold">Latest Draft</h2>
            </div>
            @if ($latestRecommendation)
                <span class="text-sm  text-ef-muted">{{ ucfirst($latestRecommendation->status) }}</span>
            @endif
        </div>
        @if ($latestRecommendation)
            <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-ef-accent-soft">{{ $latestRecommendation->albumRecommendations->count() }} albums
                    ready for review.</p>
                <div class="flex flex-wrap gap-4"><a class="font-semibold text-ef-accent-strong hover:underline"
                        href="{{ route('recommendations.review.page', $latestRecommendation, false) }}">Review Draft
                        →</a><a class="font-semibold text-ef-accent-strong hover:underline"
                        href="{{ route('recommendations.audit', ['run' => $latestRecommendation->id], false) }}">Audit
                        Coverage →</a></div>
        </div>@else<p class="mt-6 text-sm text-ef-muted">No recommendation draft has been generated yet.</p>
        @endif
    </section>
@endsection
