@extends('layouts.portal')

@section('title', 'Inzendingen')

@section('content')
<section class="portal-page page-dashboard">
    <div class="portal-page__inner">
        @include('portal.partials.header', ['title' => 'Inzendingen', 'crumbs' => ['Beheer' => route('admin.dashboard'), 'Inzendingen' => null], 'subtitle' => 'Offerteaanvragen, informatieaanvragen en sollicitaties via de website.'])

        <section class="card dash-section">
            <div class="portal-filters">
                <a class="btn {{ $type ? 'btn-ghost' : 'btn-primary' }}" href="{{ route('admin.submissions.index') }}">Alles</a>
                @foreach (\App\Models\Submission::TYPES as $key => $label)
                    <a class="btn {{ $type === $key ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('admin.submissions.index', ['type' => $key]) }}">{{ $label }}</a>
                @endforeach
            </div>
        </section>

        <section class="card dash-section">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Datum</th><th>Type</th><th>Naam</th><th>E-mail</th><th>Telefoon</th><th>Acties</th></tr></thead>
                    <tbody>
                        @forelse ($submissions as $submission)
                            <tr>
                                <td>{{ $submission->created_at->format('d-m-Y H:i') }}</td>
                                <td><span class="badge {{ $submission->read_at ? 'badge-muted' : 'badge-success' }}">{{ $submission->typeLabel() }}{{ $submission->read_at ? '' : ' • nieuw' }}</span></td>
                                <td><strong>{{ $submission->name }}</strong></td>
                                <td><a href="mailto:{{ $submission->email }}">{{ $submission->email }}</a></td>
                                <td>{{ $submission->phone }}</td>
                                <td><div class="admin-actions"><a class="btn btn-primary" href="{{ route('admin.submissions.show', $submission) }}">Bekijken</a></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Nog geen inzendingen.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $submissions->links() }}</div>
        </section>
    </div>
</section>
@endsection
