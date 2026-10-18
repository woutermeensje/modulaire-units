@extends('layouts.site')

@section('body_class', 'archive post-type-archive-mh_unit')

@section('content')
    @include('partials.archive', [
        'items' => $units,
        'archiveTitle' => $archiveTitle,
        'listTitle' => 'Alle units',
        'card' => fn (\App\Models\Unit $unit): array => [
            'url' => $unit->url(),
            'title' => $unit->title,
            'image' => $unit->featured_image,
            'image_alt' => $unit->featured_image_alt,
            'category' => $unit->offerLabel(),
            'author' => null,
            'date' => $unit->published_at?->translatedFormat('j F Y'),
            'excerpt' => $unit->summary(30),
        ],
    ])
@endsection
