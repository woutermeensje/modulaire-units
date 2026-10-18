@extends('layouts.portal')

@php($isNew = ! $record->exists)
@section('title', $isNew ? $resource->singular.' toevoegen' : $resource->title($record))

@section('content')
<section class="portal-page">
    <div class="portal-page__inner">
        @include('portal.partials.header', [
            'title' => $isNew ? $resource->singular.' toevoegen' : $resource->singular.' bewerken',
            'crumbs' => ['Beheer' => route('admin.dashboard'), $resource->plural => route('admin.'.$resource->key.'.index'), ($isNew ? 'Nieuw' : $resource->title($record)) => null],
            'actions' => ! $isNew && ($url = $resource->publicUrl($record))
                ? new \Illuminate\Support\HtmlString('<a class="btn btn-ghost" href="'.e($url).'" target="_blank" rel="noopener"><i class="ph ph-arrow-square-out"></i> Bekijk op de website</a>')
                : null,
        ])

        <form method="POST" action="{{ $isNew ? route('admin.'.$resource->key.'.store') : route('admin.'.$resource->key.'.update', $record) }}" enctype="multipart/form-data" class="portal-form">
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            @foreach ($resource->fields($isNew ? null : $record) as $section => $fields)
                <section class="card dash-section">
                    <h2 class="card-title">{{ $section }}</h2>
                    <div class="portal-form__row admin-fields">
                        @foreach ($fields as $field)
                            <div @class(['portal-field', 'admin-field--full' => $field->fullWidth])>
                                @include('admin.fields.'.$field->type, ['field' => $field, 'record' => $record, 'value' => old($field->name, $record->{$field->name} ?? null)])
                                @if ($field->help)
                                    <p class="portal-help">{{ $field->help }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <section class="card dash-section">
                <div class="portal-actions">
                    <button type="submit" class="btn btn-primary">Opslaan</button>
                    <a href="{{ route('admin.'.$resource->key.'.index') }}" class="btn btn-ghost">Terug</a>
                </div>
            </section>
        </form>
    </div>
</section>
@endsection
