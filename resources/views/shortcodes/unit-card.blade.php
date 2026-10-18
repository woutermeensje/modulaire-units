@props(['unit'])
@php
    /** @var \App\Models\Unit $unit */
    $image = \App\Models\Media::variant($unit->featured_image, 'large');
@endphp
<article class="mh-unit-card mh-product-card">
    <a class="mh-unit-card__link mh-unit-card__media-link" href="{{ $unit->url() }}" aria-label="{{ $unit->title }}">
        <div class="mh-product-card__media mh-unit-card__media">
            @if ($offer = $unit->offerLabel())
                <span class="mh-unit-card__badge">{{ $offer }}</span>
            @endif
            @if ($image)
                <img width="800" height="534" src="{{ \App\Support\SiteUrl::media($image) }}" class="mh-product-card__image mh-unit-card__image" alt="{{ $unit->featured_image_alt ?: $unit->title }}" loading="lazy">
            @endif
        </div>
    </a>
    <div class="mh-product-card__body mh-unit-card__body">
        <h3 class="mh-product-card__title mh-product-card__title--shortcode">
            <a class="mh-unit-card__title-link" href="{{ $unit->url() }}">{{ $unit->title }}</a>
        </h3>
        @if ($unit->excerpt)
            <p class="mh-unit-card__excerpt">{{ $unit->summary() }}</p>
        @endif
        @php($tags = $unit->termsFor(\App\Models\UnitTerm::TYPE)->concat($unit->termsFor(\App\Models\UnitTerm::CONDITION)))
        @if ($tags->isNotEmpty())
            <div class="mh-unit-card__taxonomies">
                @foreach ($tags as $term)
                    <span class="mh-unit-card__tag">{{ $term->name }}</span>
                @endforeach
            </div>
        @endif
        <div class="mh-unit-card__footer">
            <div class="mh-unit-card__actions">
                <form method="post" action="{{ route('quote.add') }}" class="mh-unit-card__quote-form mh-unit-card__quote-form--stacked">
                    @csrf
                    <input type="hidden" name="type" value="unit">
                    <input type="hidden" name="id" value="{{ $unit->id }}">
                    <button type="submit" class="mh-unit-card__action-button mh-unit-card__action-button--quote mh-unit-card__quote mh-unit-card__quote-button">Toevoegen aan offerte</button>
                </form>
            </div>
        </div>
    </div>
</article>
