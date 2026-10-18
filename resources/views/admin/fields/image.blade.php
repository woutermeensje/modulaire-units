<label class="portal-label" for="{{ $field->name }}_upload">{{ $field->label }}</label>
@if ($value)
    <div class="portal-media-item">
        <img class="portal-media-image" src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($value, 'medium')) }}" alt="">
        <label class="portal-checkbox"><input type="checkbox" name="{{ $field->name }}_remove" value="1"> Afbeelding verwijderen</label>
    </div>
@endif
<input class="portal-input" id="{{ $field->name }}_upload" name="{{ $field->name }}_upload" type="file" accept="image/*">
