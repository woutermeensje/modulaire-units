<label class="portal-label" for="{{ $field->name }}_upload">{{ $field->label }}</label>
@php($images = collect($record->{$field->name} ?? [])->map(fn ($image) => is_array($image) ? $image : ['path' => $image]))
@if ($images->isNotEmpty())
    <div class="portal-gallery-grid">
        @foreach ($images as $image)
            <label>
                <img src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($image['path'], 'thumbnail')) }}" alt="">
                <span><input type="checkbox" name="{{ $field->name }}_remove[]" value="{{ $image['path'] }}"> Verwijderen</span>
            </label>
        @endforeach
    </div>
@endif
<input class="portal-input" id="{{ $field->name }}_upload" name="{{ $field->name }}_upload[]" type="file" accept="image/*" multiple>
