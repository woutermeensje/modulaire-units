@extends('layouts.portal')

@section('title', 'Instellingen')

@section('content')
<section class="portal-page">
    <div class="portal-page__inner">
        @include('portal.partials.header', ['title' => 'Instellingen', 'crumbs' => ['Beheer' => route('admin.dashboard'), 'Instellingen' => null], 'subtitle' => 'Algemene gegevens die op de hele website worden gebruikt.'])

        <section class="card dash-section">
            <form method="POST" action="{{ route('admin.settings.update') }}" class="portal-form">
                @csrf
                @method('PUT')
                @foreach ($fields as $key => $field)
                    <div class="portal-field">
                        <label class="portal-label" for="{{ $key }}">{{ $field['label'] }}</label>
                        @if (($field['type'] ?? 'text') === 'textarea')
                            <textarea class="portal-textarea" id="{{ $key }}" name="{{ $key }}" rows="3" data-quill="off">{{ old($key, $values[$key]) }}</textarea>
                        @else
                            <input class="portal-input" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}">
                        @endif
                    </div>
                @endforeach
                <div class="portal-actions">
                    <button type="submit" class="btn btn-primary">Opslaan</button>
                </div>
            </form>
        </section>
    </div>
</section>
@endsection
