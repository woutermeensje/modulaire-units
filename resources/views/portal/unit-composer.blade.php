@extends('layouts.portal')

@section('title', $title)

@section('content')
<section class="portal-page">
    <div class="portal-page__inner">
        @include('portal.partials.header', ['title' => $title, 'crumbs' => $crumbs, 'subtitle' => $subtitle])

        <section class="card dash-section">
            @include('admin.fields.configurator', [
                'field' => \App\Admin\Field::make('configuration', 'Samenstelling', 'configurator'),
                'record' => null,
                'value' => $configuration,
                'composer' => true,
                'previewUrl' => $previewUrl,
                'designUrl' => $designUrl,
                'quoteUrl' => $quoteUrl ?? null,
            ])
        </section>
    </div>
</section>
@endsection
