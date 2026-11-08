@extends('layouts.portal')

@section('title', $title)

@section('content')
<section class="portal-page">
    <div class="portal-page__inner">
        @include('portal.partials.header', ['title' => $title, 'crumbs' => $crumbs, 'subtitle' => $subtitle])

        @if ($storeUrl ?? null)
            {{-- Admins add the composed unit to the products, for the landing pages. --}}
            <form method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" class="portal-form">
                @csrf

                <section class="card dash-section">
                    <h2 class="card-title">Over de unit</h2>
                    <div class="portal-field">
                        <label class="portal-label" for="title">Titel</label>
                        <input class="portal-input" id="title" name="title" value="{{ old('title') }}" maxlength="255" placeholder="Bijv. Portiersloge of Kantoorunit" required>
                    </div>
                    <div class="portal-field">
                        <label class="portal-label" for="description">Beschrijving</label>
                        <textarea class="portal-textarea" id="description" name="description" rows="10">{{ old('description') }}</textarea>
                    </div>
                </section>

                <section class="card dash-section">
                    <h2 class="card-title">Prijzen</h2>
                    <div class="portal-field">
                        <label class="portal-label" for="price_rent_week">Huurprijs per week</label>
                        <input class="portal-input" id="price_rent_week" name="price_rent_week" value="{{ old('price_rent_week') }}" maxlength="190" placeholder="Bijv. 75">
                    </div>
                    <p class="portal-help">Op de website staat dit als "€ 75 per week"; het euroteken komt er vanzelf bij. De aanschafprijs staat altijd op aanvraag.</p>
                </section>

                <section class="card dash-section">
                    @include('admin.fields.configurator', [
                        'field' => \App\Admin\Field::make('configuration', 'Samenstelling', 'configurator'),
                        'record' => null,
                        'value' => $configuration,
                        'composer' => true,
                        'ai' => false,
                        'usage' => false,
                        'previewUrl' => $previewUrl,
                        'storeUrl' => $storeUrl,
                    ])
                </section>

                <section class="card dash-section">
                    <div class="portal-actions">
                        <button type="submit" class="btn btn-primary"><i class="ph ph-plus-circle"></i> Unit toevoegen aan producten</button>
                        <a class="btn btn-ghost" href="{{ route('my-used-listings.index') }}">Annuleren</a>
                    </div>
                </section>
            </form>
        @else
            <section class="card dash-section">
                @include('admin.fields.configurator', [
                    'field' => \App\Admin\Field::make('configuration', 'Samenstelling', 'configurator'),
                    'record' => null,
                    'value' => $configuration,
                    'composer' => true,
                    'ai' => $ai ?? false,
                    'usage' => false,
                    'previewUrl' => $previewUrl,
                    'designUrl' => $designUrl ?? null,
                    'quoteUrl' => $quoteUrl ?? null,
                ])
            </section>
        @endif
    </div>
</section>
@endsection
