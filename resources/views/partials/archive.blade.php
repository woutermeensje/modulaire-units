@php
    /**
     * Shared archive layout of the original theme (blog, units, taxonomies, authors).
     *
     * @var \Illuminate\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection $items
     * @var \Closure(mixed): array{url: string, title: string, image: ?string, image_alt: ?string, category: ?string, author: ?string, date: ?string, excerpt: ?string} $card
     */
    $cards = collect($items instanceof \Illuminate\Contracts\Pagination\Paginator ? $items->items() : $items)->map($card)->values();
    $isFirstPage = ! ($items instanceof \Illuminate\Contracts\Pagination\Paginator) || $items->currentPage() === 1;
    $heroes = $isFirstPage ? $cards->take(3) : collect();
    $rows = $isFirstPage ? $cards->slice(3) : $cards;
    $placeholder = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#25476B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m3 9 4-4 4 4 4-4 4 4"/><circle cx="8.5" cy="14.5" r="1.5"/><path d="m21 15-5-5-5 5"/></svg>';
@endphp
<main id="content" class="site-main mh-blog-page">
    @isset($archiveTitle)
        <h1 class="screen-reader-text">{{ $archiveTitle }}</h1>
    @endisset

    @if ($cards->isEmpty())
        <div class="mh-blog__lower">
            <div class="mh-blog__main">
                <p class="mh-blog__empty">Er zijn hier nog geen items gepubliceerd.</p>
            </div>
            @include('partials.archive-sidebar')
        </div>
    @else
        @if ($heroes->isNotEmpty())
            <div class="mh-blog__heroes">
                @foreach ($heroes as $index => $hero)
                    @if ($index === 1)
                        <div class="mh-blog__heroes-side">
                    @endif
                    <a href="{{ $hero['url'] }}" class="mh-blog__hero {{ $index === 0 ? 'mh-blog__hero--main' : 'mh-blog__hero--side' }}" aria-label="{{ $hero['title'] }}">
                        @if ($hero['image'])
                            <img @if ($index === 0) fetchpriority="high" @else loading="lazy" @endif class="mh-blog__hero-img" src="{{ \App\Support\SiteUrl::media($hero['image']) }}" alt="{{ $hero['image_alt'] ?: $hero['title'] }}">
                        @else
                            <div class="mh-blog__hero-placeholder">{!! $placeholder !!}</div>
                        @endif
                        <div class="mh-blog__hero-overlay"></div>
                        <div class="mh-blog__hero-body">
                            @if ($hero['category'])
                                <span class="mh-blog__hero-cat">{{ $hero['category'] }}</span>
                            @endif
                            <h2 class="mh-blog__hero-title mh-blog__heading">{{ $hero['title'] }}</h2>
                            <span class="mh-blog__hero-meta">
                                @if ($hero['author'])
                                    <span class="mh-blog__hero-author">{{ $hero['author'] }}</span>
                                    <span class="mh-blog__meta-sep" aria-hidden="true">·</span>
                                @endif
                                {{ $hero['date'] }}
                            </span>
                        </div>
                    </a>
                    @if ($index >= 1 && $loop->last)
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="mh-blog__lower">
            <div class="mh-blog__main">
                @if ($rows->isNotEmpty())
                    <h2 class="mh-blog__list-title">{{ $listTitle ?? 'Alle artikelen' }}</h2>
                    <div class="mh-blog__list">
                        @foreach ($rows as $row)
                            <a href="{{ $row['url'] }}" class="mh-blog__row">
                                <div class="mh-blog__row-img-wrap">
                                    @if ($row['image'])
                                        <img class="mh-blog__row-img" src="{{ \App\Support\SiteUrl::media($row['image']) }}" alt="{{ $row['image_alt'] ?: $row['title'] }}" loading="lazy">
                                    @else
                                        <div class="mh-blog__row-placeholder">{!! $placeholder !!}</div>
                                    @endif
                                </div>
                                <div class="mh-blog__row-body">
                                    @if ($row['category'])
                                        <span class="mh-blog__row-cat">{{ $row['category'] }}</span>
                                    @endif
                                    <h3 class="mh-blog__row-title mh-blog__heading">{{ $row['title'] }}</h3>
                                    @if ($row['excerpt'])
                                        <p class="mh-blog__row-excerpt">{{ $row['excerpt'] }}</p>
                                    @endif
                                    <span class="mh-blog__row-meta">
                                        @if ($row['author'])
                                            <span class="mh-blog__row-author">{{ $row['author'] }}</span>
                                            <span class="mh-blog__meta-sep" aria-hidden="true">·</span>
                                        @endif
                                        {{ $row['date'] }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif

                @if ($items instanceof \Illuminate\Contracts\Pagination\Paginator && $items->hasPages())
                    <nav class="mh-blog__pagination" aria-label="Paginering">
                        @if ($items->previousPageUrl())
                            <a class="mh-blog__page-link" href="{{ $items->previousPageUrl() }}">&larr; Vorige</a>
                        @endif
                        <span class="mh-blog__page-current">Pagina {{ $items->currentPage() }} van {{ $items->lastPage() }}</span>
                        @if ($items->nextPageUrl())
                            <a class="mh-blog__page-link" href="{{ $items->nextPageUrl() }}">Volgende &rarr;</a>
                        @endif
                    </nav>
                @endif
            </div>
            @include('partials.archive-sidebar')
        </div>
    @endif
</main>
