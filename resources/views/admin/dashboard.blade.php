@extends('layouts.portal')

@section('title', 'Beheer')

@section('content')
<section class="portal-page page-dashboard">
    <div class="portal-page__inner">
        @include('portal.partials.header', ['title' => 'Beheer', 'crumbs' => ['Beheer' => null], 'subtitle' => 'Beheer de inhoud van '.\App\Models\Setting::get('site_name').'.'])

        <section class="sales-card-grid">
            @foreach ($stats as $stat)
                <article class="card sales-card">
                    <div class="sales-card__icon"><i class="ph {{ $stat['icon'] }}"></i></div>
                    <p class="sales-card__meta">{{ $stat['label'] }}</p>
                    <h2 class="sales-card__title">{{ $stat['value'] }}</h2>
                    <a class="sales-card__link" href="{{ route($stat['route']) }}">
                        <span>Beheren</span>
                        <i class="ph ph-arrow-right"></i>
                    </a>
                </article>
            @endforeach
        </section>

        <section class="card dash-section">
            <h2 class="card-title">Laatste inzendingen</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Datum</th><th>Type</th><th>Naam</th><th>E-mail</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($latestSubmissions as $submission)
                            <tr>
                                <td>{{ $submission->created_at->format('d-m-Y H:i') }}</td>
                                <td><span class="badge {{ $submission->read_at ? 'badge-muted' : 'badge-success' }}">{{ $submission->typeLabel() }}</span></td>
                                <td>{{ $submission->name }}</td>
                                <td>{{ $submission->email }}</td>
                                <td><a class="btn btn-ghost" href="{{ route('admin.submissions.show', $submission) }}">Bekijken</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Nog geen inzendingen.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>
@endsection
