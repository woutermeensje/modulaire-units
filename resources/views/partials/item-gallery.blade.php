@php
    /** @var list<array{path: string, alt: string|null}> $images */
    /** @var array<string, mixed>|null $viewer  Data for the 3D viewer of a configured unit. */
    $viewer ??= null;
@endphp
<div class="mh-col-left">
    <div class="mh-gallery-preview" id="mh-open-gallery">
        @forelse ($images as $image)
            <img width="800" height="534" src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($image['path'], 'large')) }}" @class(['is-active' => $loop->first]) alt="{{ $image['alt'] ?: $title }}" @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>
        @empty
            <div class="mh-gallery-empty"></div>
        @endforelse
        @if (count($images) > 1)
            <button type="button" class="mh-preview-arrow mh-preview-arrow--prev" id="mh-preview-prev" aria-label="Vorige afbeelding">&#8592;</button>
            <button type="button" class="mh-preview-arrow mh-preview-arrow--next" id="mh-preview-next" aria-label="Volgende afbeelding">&#8594;</button>
            <div class="mh-preview-counter" id="mh-preview-counter">
                @foreach ($images as $image)
                    <span @class(['is-active' => $loop->first])></span>
                @endforeach
            </div>
        @endif
    </div>
    @if ($viewer !== null)
        <div class="mh-unit-viewer" data-unit-viewer='@json($viewer)' role="img" aria-label="Draaibare 3D-weergave van {{ $title }}" hidden></div>
        <div class="mh-view-toggle">
            <button type="button" class="mh-view-toggle__button" data-unit-viewer-open>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                Bekijk in 3D
            </button>
            <button type="button" class="mh-view-toggle__button" data-unit-viewer-close hidden>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Terug naar foto's
            </button>
            <span class="mh-view-toggle__hint" data-unit-viewer-hint hidden>Sleep om de unit te draaien</span>
        </div>
    @endif
    @if (count($images) > 0)
        <div class="mh-lightbox" id="mh-lightbox" role="dialog" aria-modal="true" aria-label="Afbeeldingen">
            <div class="mh-lightbox-inner">
                <button class="mh-lightbox-close" id="mh-lightbox-close" aria-label="Sluiten">&times;</button>
                <button class="mh-lightbox-arrow mh-lightbox-arrow--prev" id="mh-arrow-prev" aria-label="Vorige">&#8592;</button>
                <div class="mh-lightbox-strip" id="mh-lightbox-strip">
                    @foreach ($images as $image)
                        <img src="{{ \App\Support\SiteUrl::media($image['path']) }}" alt="{{ $image['alt'] ?: $title }}" loading="lazy">
                    @endforeach
                </div>
                <button class="mh-lightbox-arrow mh-lightbox-arrow--next" id="mh-arrow-next" aria-label="Volgende">&#8594;</button>
            </div>
        </div>
    @endif
</div>
