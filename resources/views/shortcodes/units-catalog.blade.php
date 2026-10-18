@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Unit> $units */
    $ancestors = collect();
    for ($parent = $page?->parent; $parent; $parent = $parent->parent) {
        $ancestors->prepend($parent);
    }
@endphp
<div class="mh-units-catalog__intro">
    <div class="mh-units-catalog__intro-main">
        <nav class="mh-units-catalog__breadcrumbs mh-units-shortcode__breadcrumbs" aria-label="Breadcrumb">
            <span>
                <span><a href="{{ url('/') }}/">Home</a></span>
                @foreach ($ancestors as $ancestor)
                    » <span><a href="{{ $ancestor->url() }}">{{ $ancestor->title }}</a></span>
                @endforeach
                » <span class="breadcrumb_last" aria-current="page">{{ $title }}</span>
            </span>
        </nav>
        @if ($title)
            <h1 class="mh-units-catalog__title">{{ $title }}</h1>
        @endif
        <p class="mh-units-catalog__count">{{ $units->count() }} {{ $units->count() === 1 ? 'resultaat' : 'resultaten' }}</p>
    </div>
</div>
<div class="mh-catalog-layout mh-units-catalog-layout mh-units-catalog">
    <aside class="mh-catalog-sidebar mh-units-catalog__sidebar" aria-label="Unit filters">
        <div class="mh-catalog-sidebar-inner mh-units-catalog__sidebar-inner">
            <form class="mh-units-filter-form" method="get">
                <input type="hidden" name="mh_units_filter" value="1">
                <div class="mh-catalog-block mh-units-catalog__filter-block">
                    <label class="mh-units-filter-label" for="mh_search">Zoek</label>
                    <input id="mh_search" name="mh_search" type="text" class="mh-units-catalog__search" placeholder="Zoek op naam, trefwoord..." value="{{ $search }}">
                </div>
                <fieldset class="mh-catalog-block mh-units-catalog__filter-block mh-units-filter-field--types">
                    <legend>Type unit</legend>
                    <div class="mh-units-catalog__select" data-name="mh_type[]">
                        <button type="button" class="mh-units-catalog__select-btn" aria-haspopup="listbox" aria-expanded="false">
                            <span class="mh-units-catalog__select-label is-placeholder">Selecteer type unit</span>
                            <svg class="mh-units-catalog__select-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
                        </button>
                        <div class="mh-units-catalog__select-panel" role="listbox" aria-multiselectable="true">
                            <div class="mh-units-catalog__select-search-wrap">
                                <input type="search" class="mh-units-catalog__select-search" placeholder="Zoek type unit...">
                            </div>
                            <div class="mh-units-catalog__select-options">
                                @foreach ($types as $type)
                                    <div class="mh-units-catalog__select-option" role="option" aria-selected="{{ in_array($type->slug, $selectedTypes, true) ? 'true' : 'false' }}" data-value="{{ $type->slug }}" data-label="{{ $type->name }}">
                                        <span>{{ $type->name }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mh-units-catalog__select-empty">Geen types gevonden.</div>
                        </div>
                        <div class="mh-units-catalog__select-tags"></div>
                        <div class="mh-units-catalog__select-hidden">
                            @foreach ($selectedTypes as $selectedType)
                                <input type="hidden" name="mh_type[]" value="{{ $selectedType }}">
                            @endforeach
                        </div>
                    </div>
                </fieldset>
                <div class="mh-catalog-block mh-catalog-block--actions mh-units-catalog__actions">
                    <button type="submit" class="mh-units-catalog__submit">Zoeken</button>
                </div>
            </form>
        </div>
    </aside>
    <div class="mh-catalog-grid-wrap mh-units-catalog-grid-wrap">
        @include('shortcodes.units-grid', ['units' => $units])
    </div>
</div>
