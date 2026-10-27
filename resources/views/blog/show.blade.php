@extends('layouts.site')

@section('body_class', 'single single-post postid-'.$post->id)

@section('content')
    <main id="content" class="site-main mh-post">
        <article class="mh-post__article">
            <nav class="mhu-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ url('/') }}/">Home</a>
                <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
                <a href="{{ route('blog.index') }}/">Blog</a>
                <span class="mhu-breadcrumb__sep" aria-hidden="true">›</span>
                <span aria-current="page">{{ $post->title }}</span>
            </nav>

            <header class="mh-post__header">
                @if ($post->category)
                    <a class="mh-post__category" href="{{ $post->category->url() }}">{{ $post->category->name }}</a>
                @endif
                <h1 class="mh-post__title">{{ $post->title }}</h1>
                <p class="mh-post__meta">
                    @if ($post->author)
                        <a href="{{ $post->author->url() }}">{{ $post->author->name }}</a>
                        <span class="mh-blog__meta-sep" aria-hidden="true">·</span>
                    @endif
                    @if ($post->published_at)
                        <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->translatedFormat('j F Y') }}</time>
                    @endif
                </p>
            </header>

            @if ($post->featured_image)
                <figure class="mh-post__image">
                    <img src="{{ \App\Support\SiteUrl::media($post->featured_image) }}" alt="{{ $post->featured_image_alt ?: $post->title }}" fetchpriority="high">
                </figure>
            @endif

            <div class="mh-post__content entry-content text-content">
                {!! $content !!}
            </div>
        </article>

        @if ($relatedPosts->isNotEmpty())
            <section class="mh-post__related">
                <h2 class="mh-blog__list-title">Lees ook</h2>
                <div class="mh-post__related-grid">
                    @foreach ($relatedPosts as $related)
                        <a class="mh-post__related-card" href="{{ $related->url() }}">
                            <span class="mh-post__related-date">{{ $related->published_at?->translatedFormat('j F Y') }}</span>
                            <strong>{{ $related->title }}</strong>
                            <span class="mh-post__related-excerpt">{{ $related->summary(22) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'datePublished' => $post->published_at?->toIso8601String(),
        'dateModified' => $post->updated_at?->toIso8601String(),
        'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author->name] : null,
        'image' => $post->featured_image ? \App\Support\SiteUrl::absolute(\App\Support\SiteUrl::media($post->featured_image)) : null,
        'mainEntityOfPage' => \App\Support\SiteUrl::absolute($post->url()),
        'publisher' => ['@id' => url('/').'/#organization'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection
