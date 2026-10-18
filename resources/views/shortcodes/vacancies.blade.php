<div class="job_listings">
    <div class="filter-header">
        <h2>Werken bij Modulairehuisvesting</h2>
        <p>Bekijk onze openstaande vacatures en word onderdeel van ons team.</p>
    </div>
    @if ($vacancies->isEmpty())
        <p class="no_job_listings_found">Er zijn op dit moment geen openstaande vacatures.</p>
    @else
        <ul class="job_listings">
            @foreach ($vacancies as $vacancy)
                <li class="job_listing">
                    <a href="{{ $vacancy->url() }}">
                        @if ($vacancy->featured_image)
                            <img class="company_logo" src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($vacancy->featured_image, 'thumbnail')) }}" alt="{{ $vacancy->title }}" loading="lazy">
                        @endif
                        <div class="position">
                            <h3>{{ $vacancy->title }}</h3>
                            <div class="company"><strong>{{ \App\Models\Setting::get('site_name') }}</strong></div>
                        </div>
                        <div class="location">{{ $vacancy->location }}</div>
                        <ul class="meta">
                            @if ($vacancy->employment_type)
                                <li class="job-type">{{ $vacancy->employment_type }}</li>
                            @endif
                            @if ($vacancy->published_at)
                                <li class="date"><time datetime="{{ $vacancy->published_at->toDateString() }}">{{ $vacancy->published_at->translatedFormat('j F Y') }}</time></li>
                            @endif
                        </ul>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
