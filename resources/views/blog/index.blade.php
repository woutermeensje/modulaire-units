@extends('layouts.site')

@section('body_class', 'blog archive')

@section('content')
    @include('partials.archive', [
        'items' => $posts,
        'archiveTitle' => $archiveTitle,
        'card' => fn (\App\Models\Post $post): array => [
            'url' => $post->url(),
            'title' => $post->title,
            'image' => $post->featured_image,
            'image_alt' => $post->featured_image_alt,
            'category' => $post->category?->name,
            'author' => $post->author?->name,
            'date' => $post->published_at?->translatedFormat('d F Y'),
            'excerpt' => $post->summary(),
        ],
    ])
@endsection
