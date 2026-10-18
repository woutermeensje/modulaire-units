@php
    $selected = collect(old('terms', $record->exists ? $record->terms->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $termsByTaxonomy = \App\Models\UnitTerm::query()->orderBy('sort')->orderBy('name')->get()->groupBy('taxonomy');
@endphp
<span class="portal-label">{{ $field->label }}</span>
<div class="admin-terms">
    @foreach (\App\Models\UnitTerm::TAXONOMIES as $taxonomy => $config)
        <fieldset class="admin-terms__group">
            <legend class="portal-label">{{ $config['label'] }}</legend>
            @foreach ($termsByTaxonomy[$taxonomy] ?? [] as $term)
                <label class="portal-checkbox">
                    <input type="checkbox" name="terms[]" value="{{ $term->id }}" @checked($selected->contains($term->id))>
                    {{ $term->name }}
                </label>
            @endforeach
        </fieldset>
    @endforeach
</div>
<p class="portal-help">Nieuwe types of condities voeg je toe onder <a href="{{ route('admin.unit-terms.index') }}">Unit-kenmerken</a>.</p>
