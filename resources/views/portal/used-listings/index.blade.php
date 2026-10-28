{{-- Overzicht van de gebruikte units die een organisatie heeft aangeboden. --}}
@extends('layouts.portal')

@section('title', 'Mijn units')

@php($statusLabels = ['pending' => 'Ter beoordeling', 'published' => 'Gepubliceerd', 'draft' => 'Offline'])

@section('content')
<section class="portal-page page-dashboard">
    <div class="portal-page__inner">
        @include('portal.partials.header', [
            'title' => 'Mijn units',
            'crumbs' => ['Dashboard' => route('dashboard'), 'Mijn units' => null],
            'subtitle' => 'Gebruikte units die je gratis aanbiedt op de pagina Gebruikt. Nieuwe aanbiedingen worden eerst door ons gecontroleerd.',
            'actions' => new \Illuminate\Support\HtmlString('<a class="btn btn-primary" href="'.e(route('my-used-listings.create')).'"><i class="ph ph-plus"></i> Units aanbieden</a>'),
        ])

        <section class="card dash-section">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Titel</th>
                            <th>Locatie</th>
                            <th>Prijs</th>
                            <th>Status</th>
                            <th>Aangeboden</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($listings as $listing)
                            <tr>
                                <td>
                                    @if ($listing->coverImage())
                                        <img class="portal-thumb" src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($listing->coverImage(), 'thumbnail')) }}" alt="">
                                    @endif
                                </td>
                                <td><strong>{{ $listing->title }}</strong></td>
                                <td>{{ $listing->location }}</td>
                                <td>{{ $listing->formattedPrice() }}</td>
                                <td><span class="badge {{ $listing->status === 'published' ? 'badge-success' : 'badge-muted' }}">{{ $statusLabels[$listing->status] ?? $listing->status }}</span></td>
                                <td>{{ $listing->created_at?->format('d-m-Y') }}</td>
                                <td>
                                    @if ($listing->isPublished())
                                        <a class="btn btn-ghost" href="{{ $listing->url() }}" target="_blank" rel="noopener">Bekijken</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7">Je hebt nog geen units aangeboden. <a href="{{ route('my-used-listings.create') }}">Bied je eerste unit gratis aan.</a></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>
@endsection
