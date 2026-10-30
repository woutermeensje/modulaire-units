@php
    $selected = collect(old('terms', $record->exists ? $record->terms->pluck('id')->all() : []))->filter()->map(fn ($id) => (int) $id);
    $termsByTaxonomy = \App\Models\UnitTerm::query()->orderBy('sort')->orderBy('name')->get()->groupBy('taxonomy');
@endphp
<span class="portal-label">{{ $field->label }}</span>
<div class="admin-terms">
    @foreach (\App\Models\UnitTerm::TAXONOMIES as $taxonomy => $config)
        @php($terms = $termsByTaxonomy[$taxonomy] ?? collect())
        <div class="admin-terms__group">
            <label class="portal-label" for="terms_{{ $taxonomy }}">{{ $config['label'] }}</label>
            <select class="portal-input" id="terms_{{ $taxonomy }}" name="terms[]" data-search-select>
                <option value="">Geen</option>
                @foreach ($terms as $term)
                    <option value="{{ $term->id }}" @selected($term->id === $terms->pluck('id')->first(fn (int $id): bool => $selected->contains($id)))>{{ $term->name }}</option>
                @endforeach
            </select>
        </div>
    @endforeach
</div>
<p class="portal-help">Nieuwe types of condities voeg je toe onder <a href="{{ route('admin.unit-terms.index') }}">Unit-kenmerken</a>.</p>
