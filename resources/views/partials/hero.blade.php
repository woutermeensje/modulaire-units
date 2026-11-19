@php
    /** @var array{image?: string, eyebrow?: string, title?: string, subtitle?: string, button_text?: string, button_url?: string} $hero */
    /** @var bool $titleIsHeading  Whether the hero title is the h1 of the page. */
    $titleIsHeading ??= true;
    $heroImage = \App\Support\SiteUrl::media($hero['image'] ?? null);
@endphp
<div class="mh-lp">
    <section class="mh-home-hero" @if ($heroImage) style="background-image: url('{{ $heroImage }}');" @endif>
        <div class="mh-home-hero__inner">
            @if (! empty($hero['eyebrow']))
                <span class="mh-home-hero__eyebrow">{{ $hero['eyebrow'] }}</span>
            @endif
            <div class="mh-home-hero__title-wrap">
                @if (! empty($hero['title']))
                    {{-- Only the home page uses the hero title as its h1; elsewhere the page has its own heading. --}}
                    <{{ $titleIsHeading ? 'h1' : 'p' }} class="mh-home-hero__title">{!! $hero['title'] !!}</{{ $titleIsHeading ? 'h1' : 'p' }}>
                @endif
                @if (! empty($hero['subtitle']))
                    <p class="mh-home-hero__subtitle">{{ $hero['subtitle'] }}</p>
                @endif
                @if (! empty($hero['button_text']) && ! empty($hero['button_url']))
                    <div class="mh-home-hero__actions">
                        <a href="{{ $hero['button_url'] }}" class="mh-lp__btn mh-lp__btn--outline">{{ $hero['button_text'] }}</a>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
