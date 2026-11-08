{{-- Overzicht van de units in het account: voor organisaties hun aangeboden gebruikte units, voor admins de units die ze hebben samengesteld. --}}
@extends('layouts.portal')

@section('title', 'Mijn units')

@php($statusLabels = ['pending' => 'Ter beoordeling', 'published' => 'Gepubliceerd', 'draft' => 'Offline'])
@php($isAdmin = auth()->user()->isAdmin())

@section('content')
<section class="portal-page page-dashboard">
    <div class="portal-page__inner">
        @if ($isAdmin)
            @include('portal.partials.header', [
                'title' => 'Mijn units',
                'crumbs' => ['Dashboard' => route('dashboard'), 'Mijn units' => null],
                'subtitle' => 'Units die je met de unit-samensteller hebt gemaakt. Gepubliceerde units staan in het overzicht op de pagina Modulaire units.',
                'actions' => new \Illuminate\Support\HtmlString('<a class="btn btn-primary" href="'.e(route('unit-composer')).'"><i class="ph ph-plus"></i> Unit samenstellen</a>'),
            ])

            <section class="card dash-section">
                <div class="table-wrap table-wrap--dropdowns">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Titel</th>
                                <th>Status</th>
                                <th>Toegevoegd</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($units as $unit)
                                <tr>
                                    <td>
                                        @if ($unit->featured_image)
                                            <img class="portal-thumb" src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($unit->featured_image, 'thumbnail')) }}" alt="">
                                        @endif
                                    </td>
                                    <td><strong>{{ $unit->title }}</strong></td>
                                    <td>
                                        <form method="POST" action="{{ route('composed-units.status', $unit) }}" class="portal-status-form">
                                            @csrf
                                            @method('PATCH')
                                            <select class="portal-input" name="status" aria-label="Status van {{ $unit->title }}" data-search-select data-submit-on-change>
                                                @foreach (\App\Models\Unit::STATUSES as $status => $statusLabel)
                                                    <option value="{{ $status }}" @selected($unit->status === $status)>{{ $statusLabel }}</option>
                                                @endforeach
                                            </select>
                                            <noscript><button type="submit" class="btn btn-ghost">Opslaan</button></noscript>
                                        </form>
                                    </td>
                                    <td>{{ $unit->created_at?->format('d-m-Y') }}</td>
                                    <td>
                                        <a class="btn btn-ghost" href="{{ route('admin.units.edit', $unit->id) }}">Bewerken</a>
                                        @if ($unit->isPublished())
                                            <a class="btn btn-ghost" href="{{ $unit->url() }}" target="_blank" rel="noopener">Bekijken</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5">Nog geen units samengesteld. <a href="{{ route('unit-composer') }}">Stel een unit samen.</a></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @else
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
        @endif
    </div>
</section>
@endsection
