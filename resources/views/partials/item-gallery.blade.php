@php
    /** @var list<array{path: string, alt: string|null}> $images */
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
