@extends('layouts.portal')

@section('title', $submission->typeLabel())

@section('content')
<section class="portal-page">
    <div class="portal-page__inner">
        @include('portal.partials.header', ['title' => $submission->typeLabel().' van '.$submission->name, 'crumbs' => ['Beheer' => route('admin.dashboard'), 'Inzendingen' => route('admin.submissions.index'), $submission->name => null]])

        <section class="card dash-section">
            <dl class="dl-grid">
                <dt>Ontvangen</dt><dd>{{ $submission->created_at->format('d-m-Y H:i') }}</dd>
                <dt>Naam</dt><dd>{{ $submission->name }}</dd>
                <dt>E-mail</dt><dd><a href="mailto:{{ $submission->email }}">{{ $submission->email }}</a></dd>
                @if ($submission->phone)
                    <dt>Telefoon</dt><dd><a href="tel:{{ $submission->phone }}">{{ $submission->phone }}</a></dd>
                @endif
                @foreach (collect($submission->data)->except(['cv_path', 'cv_name']) as $label => $value)
                    <dt>{{ ucfirst(str_replace('_', ' ', $label)) }}</dt><dd>{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
                @endforeach
                @if (! empty($submission->data['cv_path']))
                    <dt>CV</dt><dd><a class="btn btn-ghost" href="{{ route('admin.submissions.download', $submission) }}"><i class="ph ph-download-simple"></i> {{ $submission->data['cv_name'] ?? 'Download' }}</a></dd>
                @endif
                @if ($submission->message)
                    <dt>Bericht</dt><dd>{{ $submission->message }}</dd>
                @endif
                @if ($submission->source_url)
                    <dt>Pagina</dt><dd><a href="{{ $submission->source_url }}" target="_blank" rel="noopener">{{ $submission->source_url }}</a></dd>
                @endif
            </dl>
        </section>

        @if ($submission->items)
            <section class="card dash-section">
                <h2 class="card-title">Offerte-items</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Item</th><th>Aantal</th></tr></thead>
                        <tbody>
                            @foreach ($submission->items as $item)
                                <tr><td><a href="{{ $item['url'] }}" target="_blank" rel="noopener">{{ $item['title'] }}</a></td><td>{{ $item['quantity'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="card dash-section">
            <div class="portal-actions">
                <a class="btn btn-primary" href="mailto:{{ $submission->email }}">Beantwoorden per e-mail</a>
                <a class="btn btn-ghost" href="{{ route('admin.submissions.index') }}">Terug</a>
                <form method="POST" action="{{ route('admin.submissions.destroy', $submission) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-ghost" data-confirm="Inzending verwijderen?">Verwijderen</button>
                </form>
            </div>
        </section>
    </div>
</section>
@endsection
