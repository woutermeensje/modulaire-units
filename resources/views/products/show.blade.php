@extends('layouts.site')

@section('body_class', 'single single-product')

@section('content')
    <main class="mhu-single-page" id="content">
        <nav class="mhu-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ url('/') }}/">Home</a>
            <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
            <a href="{{ route('products.index') }}/">Winkel</a>
            <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $product->title }}</span>
        </nav>

        <div class="mh-product-wrapper">
            @include('partials.item-gallery', ['images' => $product->images(), 'title' => $product->title])

            <div class="mh-col-right">
                <h1 class="product_title entry-title">{{ $product->title }}</h1>
                @if ($product->price)
                    <p class="price">{{ $product->price }}</p>
                @endif

                @include('partials.quote-cta', ['type' => 'product', 'id' => $product->id])

                @if ($product->short_description)
                    <div class="mh-intro-text">{!! $product->short_description !!}</div>
                @endif
            </div>
        </div>

        @if (trim(strip_tags((string) $product->content)) !== '')
            <div class="mh-col-description">
                <h2>Beschrijving</h2>
                <div class="text-content">{!! $content !!}</div>
            </div>
        @endif

        @if ($relatedProducts->isNotEmpty())
            <div class="mh-col-description mh-related">
                <h2>Gerelateerde producten</h2>
                <div class="mh-related-grid">
                    @foreach ($relatedProducts as $related)
                        <a href="{{ $related->url() }}" class="mh-related-item">
                            <div class="mh-related-thumb">
                                @if ($related->featured_image)
                                    <img width="300" height="200" src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($related->featured_image, 'medium')) }}" alt="{{ $related->featured_image_alt ?: $related->title }}" loading="lazy">
                                @endif
                            </div>
                            <span class="mh-related-name">{{ $related->title }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </main>
@endsection
