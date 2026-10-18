@extends('layouts.portal')

@section('title', $resource->plural)

@section('content')
<section class="portal-page page-dashboard">
    <div class="portal-page__inner">
        @include('portal.partials.header', [
            'title' => $resource->plural,
            'crumbs' => ['Beheer' => route('admin.dashboard'), $resource->plural => null],
            'subtitle' => $resource->description,
        ])

        <section class="card dash-section">
            <div class="portal-filters">
                <a href="{{ route('admin.'.$resource->key.'.create') }}" class="btn btn-primary"><i class="ph ph-plus"></i> {{ $resource->singular }} toevoegen</a>
                <form method="GET" class="portal-filters">
                    <input class="portal-input" type="search" name="q" value="{{ $search }}" placeholder="Zoeken…">
                    <button class="btn btn-ghost" type="submit">Zoeken</button>
                </form>
            </div>
        </section>

        <section class="card dash-section">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            @foreach (array_keys($resource->columns()) as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                @foreach ($resource->columns() as $label => $column)
                                    @php($value = $column($record))
                                    <td>
                                        @if ($label === 'Status')
                                            <span class="badge {{ $value === 'published' ? 'badge-success' : 'badge-muted' }}">{{ $value === 'published' ? 'Gepubliceerd' : 'Concept' }}</span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                @endforeach
                                <td>
                                    <div class="admin-actions">
                                        @if ($url = $resource->publicUrl($record))
                                            <a class="btn btn-ghost" href="{{ $url }}" target="_blank" rel="noopener">Bekijken</a>
                                        @endif
                                        <a class="btn btn-primary" href="{{ route('admin.'.$resource->key.'.edit', $record) }}">Bewerken</a>
                                        <form method="POST" action="{{ route('admin.'.$resource->key.'.destroy', $record) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-ghost" data-confirm="{{ $resource->singular }} verwijderen?">Verwijderen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($resource->columns()) + 1 }}">Nog niets toegevoegd.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $records->links() }}</div>
        </section>
    </div>
</section>
@endsection
