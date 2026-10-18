@php($hero = old('hero', $record->hero ?? []))
<span class="portal-label">{{ $field->label }}</span>
<div class="portal-form__row admin-fields">
    <div class="portal-field">
        <label class="portal-label" for="hero_eyebrow">Bovenkop</label>
        <input class="portal-input" id="hero_eyebrow" name="hero[eyebrow]" value="{{ $hero['eyebrow'] ?? '' }}">
    </div>
    <div class="portal-field">
        <label class="portal-label" for="hero_subtitle">Ondertitel</label>
        <input class="portal-input" id="hero_subtitle" name="hero[subtitle]" value="{{ $hero['subtitle'] ?? '' }}">
    </div>
    <div class="portal-field admin-field--full">
        <label class="portal-label" for="hero_title">Titel (HTML toegestaan, bijv. &lt;span class="mh-home-hero__accent"&gt;…&lt;/span&gt;)</label>
        <textarea class="portal-textarea" id="hero_title" name="hero[title]" rows="2" data-quill="off">{{ $hero['title'] ?? '' }}</textarea>
    </div>
    <div class="portal-field">
        <label class="portal-label" for="hero_button_text">Knoptekst</label>
        <input class="portal-input" id="hero_button_text" name="hero[button_text]" value="{{ $hero['button_text'] ?? '' }}">
    </div>
    <div class="portal-field">
        <label class="portal-label" for="hero_button_url">Knoplink</label>
        <input class="portal-input" id="hero_button_url" name="hero[button_url]" value="{{ $hero['button_url'] ?? '' }}">
    </div>
    <div class="portal-field admin-field--full">
        @include('admin.fields.image', ['field' => \App\Admin\Field::make('hero_image', 'Achtergrondafbeelding', 'image'), 'value' => $hero['image'] ?? null])
    </div>
</div>
