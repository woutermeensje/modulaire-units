<section class="mh-team-overview mh-team-overview--cols-{{ $columns }}">
    <div class="mh-team-grid">
        @foreach ($members as $member)
            <article class="mh-team-card">
                <div class="mh-team-card__photo-wrap">
                    @if ($member->photo)
                        <img loading="lazy" width="768" height="512" src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($member->photo, 'medium_large')) }}" class="mh-team-card__photo" alt="{{ $member->name }}">
                    @else
                        <div class="mh-team-card__photo mh-team-card__photo--placeholder" aria-hidden="true">{{ $member->initials() }}</div>
                    @endif
                </div>
                <div class="mh-team-card__body">
                    <div class="mh-team-card__header">
                        <h3 class="mh-team-card__name">{{ $member->name }}</h3>
                        @if ($member->role)
                            <p class="mh-team-card__role">{{ $member->role }}</p>
                        @endif
                        @if ($member->email || $member->phone || $member->linkedin_url)
                            <div class="mh-team-card__contact">
                                @if ($member->email)
                                    <a class="mh-team-card__contact-link" href="mailto:{{ $member->email }}">{{ $member->email }}</a>
                                @endif
                                @if ($member->phone)
                                    <a class="mh-team-card__contact-link mh-team-card__contact-link--phone" href="tel:{{ preg_replace('/[^0-9+]/', '', $member->phone) }}">{{ $member->phone }}</a>
                                @endif
                                @if ($member->linkedin_url)
                                    <a class="mh-team-card__contact-link" href="{{ $member->linkedin_url }}" target="_blank" rel="noopener">LinkedIn</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>
