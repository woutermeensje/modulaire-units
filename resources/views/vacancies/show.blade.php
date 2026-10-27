@extends('layouts.site')

@section('body_class', 'single single-job_listing')

@section('content')
    <main id="content" class="site-main job_listing">
        <div class="page-content">
            <div class="sj-wrap">
                <div class="sj-card">
                    <div class="sj-meta">
                        @if ($vacancy->published_at)
                            <span class="sj-chip">🗓️ {{ $vacancy->published_at->translatedFormat('j F Y') }}</span>
                        @endif
                        @if ($vacancy->location)
                            <span class="sj-chip">📍 {{ $vacancy->location }}</span>
                        @endif
                        @if ($vacancy->employment_type)
                            <span class="sj-chip">🏷️ {{ $vacancy->employment_type }}</span>
                        @endif
                    </div>
                    <header class="sj-header">
                        <h1 class="sj-title">{{ $vacancy->title }}</h1>
                    </header>
                    <div class="job_description sj-content text-content">
                        {!! $content !!}
                    </div>
                </div>

                <section class="sj-application sj-card" id="solliciteren">
                    <h2 class="sj-application-title">Reageren op deze vacature/opdracht.</h2>

                    @if (session('form_success'))
                        <div class="mh-form-notice mh-form-notice--success" role="status">{{ session('form_success') }}</div>
                    @elseif ($vacancy->is_filled)
                        <p>Deze vacature is inmiddels vervuld.</p>
                    @else
                        <form class="sj-application-form" method="post" action="{{ route('vacancies.apply', $vacancy->slug) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mh-form__honeypot" aria-hidden="true">
                                <label for="vacancy-website">Website</label>
                                <input type="text" id="vacancy-website" name="website" tabindex="-1" autocomplete="off">
                            </div>
                            <div class="sj-application-grid">
                                <label class="sj-application-field">
                                    <span>Voornaam</span>
                                    <input type="text" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" required>
                                    @error('first_name')<span class="mh-form__error">{{ $message }}</span>@enderror
                                </label>
                                <label class="sj-application-field">
                                    <span>Achternaam</span>
                                    <input type="text" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required>
                                    @error('last_name')<span class="mh-form__error">{{ $message }}</span>@enderror
                                </label>
                                <label class="sj-application-field">
                                    <span>E-mailadres</span>
                                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                                    @error('email')<span class="mh-form__error">{{ $message }}</span>@enderror
                                </label>
                                <label class="sj-application-field">
                                    <span>Telefoonnummer</span>
                                    <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" required>
                                    @error('phone')<span class="mh-form__error">{{ $message }}</span>@enderror
                                </label>
                            </div>
                            <label class="sj-application-field sj-application-field--full">
                                <span>Bericht</span>
                                <textarea name="message" rows="6" required>{{ old('message') }}</textarea>
                                @error('message')<span class="mh-form__error">{{ $message }}</span>@enderror
                            </label>
                            <label class="sj-application-field sj-application-field--full">
                                <span>CV bijlage</span>
                                <input type="file" name="cv" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
                                @error('cv')<span class="mh-form__error">{{ $message }}</span>@enderror
                            </label>
                            <button class="sj-application-submit" type="submit">Solliciteren</button>
                        </form>
                    @endif
                </section>
            </div>
        </div>
    </main>

    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'JobPosting',
        'title' => $vacancy->title,
        'description' => $vacancy->content,
        'datePosted' => $vacancy->published_at?->toDateString(),
        'hiringOrganization' => ['@type' => 'Organization', 'name' => \App\Models\Setting::get('site_name'), 'sameAs' => url('/').'/'],
        'jobLocation' => ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $vacancy->location, 'addressCountry' => 'NL']],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection
