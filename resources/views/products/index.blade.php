@extends('layouts.site')

@section('body_class', 'archive woocommerce-shop')

@section('content')
    <main class="mhu-single-page" id="content">
        <nav class="mhu-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ url('/') }}/">{{ __('Home') }}</a>
            <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ __('Winkel') }}</span>
        </nav>
        <div class="mh-col-description mh-related mh-shop">
            <h1>{{ __('Winkel') }}</h1>
            <p class="mh-shop__intro">{{ $products->count() === 1 ? __(':count resultaat', ['count' => 1]) : __(':count resultaten', ['count' => $products->count()]) }}</p>
            <div class="mh-related-grid">
                @foreach ($products as $product)
                    <a href="{{ $product->url() }}" class="mh-related-item">
                        <div class="mh-related-thumb">
                            @if ($product->featured_image)
                                <img width="300" height="200" src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($product->featured_image, 'medium_large')) }}" alt="{{ $product->featured_image_alt ?: $product->title }}" loading="lazy">
                            @endif
                        </div>
                        <span class="mh-related-name">{{ $product->title }}</span>
                        <span class="mh-related-cats">{{ __('Bekijk product') }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </main>
@endsection
