<div class="muo-grid muo-cols-{{ $columns }}">
    @foreach ($pages as $subpage)
        @php($image = \App\Support\SiteUrl::media(\App\Models\Media::variant($subpage->featured_image, 'large')))
        <a href="{{ $subpage->url() }}" class="muo-card" @if ($image) style="background-image: url('{{ $image }}')" @endif>
            <div class="muo-card__overlay"></div>
            <div class="muo-card__body">
                <h3 class="muo-card__title">{{ $subpage->title }}</h3>
            </div>
        </a>
    @endforeach
</div>
