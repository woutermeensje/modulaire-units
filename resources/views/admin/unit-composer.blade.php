@extends('layouts.portal')

@section('title', 'Unit samenstellen (test)')

@section('content')
<section class="portal-page">
    <div class="portal-page__inner">
        @include('portal.partials.header', [
            'title' => 'Unit samenstellen (test)',
            'crumbs' => ['Beheer' => route('admin.dashboard'), 'Unit samenstellen' => null],
            'subtitle' => 'Testomgeving voor het samenstellen van units zoals klanten dat straks doen. Er wordt niets opgeslagen of op de website gezet: de samenstelling staat in de link.',
        ])

        <section class="card dash-section">
            @include('admin.fields.configurator', [
                'field' => \App\Admin\Field::make('configuration', 'Samenstelling', 'configurator'),
                'record' => null,
                'value' => $configuration,
                'composer' => true,
            ])
        </section>
    </div>
</section>
@endsection
