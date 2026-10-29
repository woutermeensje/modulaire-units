@use('App\Support\UnitConfiguration')
@use('App\Support\UnitRenderer')
@use('App\Services\UnitDesigner')
@use('App\Services\UnitConfigurationImages')
@php
    $isConfigured = filled($value['size'] ?? null);
    $configuration = $isConfigured ? UnitConfiguration::normalize($value) : UnitConfiguration::defaults();
    $maxSlots = fn (string $wall): int => max(array_map(fn (string $size): int => UnitConfiguration::slotCount($size, $wall), array_keys(UnitConfiguration::SIZES)));
    $slotCounts = [];
    foreach (array_keys(UnitConfiguration::SIZES) as $size) {
        foreach (array_keys(UnitConfiguration::WALLS) as $wall) {
            $slotCounts[$size][$wall] = UnitConfiguration::slotCount($size, $wall);
        }
    }
    $slotIcons = [UnitConfiguration::EMPTY => 'ph-square', UnitConfiguration::WINDOW => 'ph-app-window', UnitConfiguration::DOOR => 'ph-door'];
    $usesPhotos = UnitConfigurationImages::usesPhotos($record?->configuration_images);
@endphp
<span class="portal-label">{{ $field->label }}</span>
<div class="unit-configurator" data-unit-configurator data-preview-url="{{ route('admin.units.preview') }}" data-design-url="{{ route('admin.units.design') }}"
     data-slot-labels='@json(UnitConfiguration::SLOTS)' data-slot-icons='@json($slotIcons)'
     data-slot-counts='@json($slotCounts)' data-backdrops='@json(UnitRenderer::BACKDROPS)'>
    <div class="unit-configurator__ai">
        <label class="portal-label" for="configuration_description"><i class="ph ph-sparkle"></i> Beschrijf de unit en laat Claude de configuratie invullen</label>
        <textarea class="portal-textarea" id="configuration_description" rows="3" data-quill="off" data-configurator-description
                  placeholder="Bijv. 6x3 kantoorunit in lichtgrijs, deur in het midden van de voorkant met aan beide kanten twee ramen, één raam in elke kopgevel, op een bouwterrein"></textarea>
        <div class="unit-configurator__ai-actions">
            <label class="portal-checkbox">
                <input type="checkbox" data-configurator-texts checked>
                Ook titel, korte omschrijving en meta description invullen (alleen lege velden)
            </label>
            <button type="button" class="btn btn-primary" data-configurator-generate @disabled(! UnitDesigner::isConfigured())>
                <i class="ph ph-sparkle"></i> Genereer met AI
            </button>
        </div>
        <p class="portal-help" role="status" data-configurator-status>
            @unless (UnitDesigner::isConfigured())
                AI is uitgeschakeld: stel ANTHROPIC_API_KEY in op de server.
            @endunless
        </p>
    </div>

    <div class="unit-configurator__controls">
        <div class="unit-configurator__options">
            <div>
                <label class="portal-label" for="configuration_size">Afmeting</label>
                <select class="portal-input" id="configuration_size" name="configuration[size]" data-configurator-size>
                    <option value="">Geen configuratie</option>
                    @foreach (UnitConfiguration::SIZES as $size => $dimensions)
                        <option value="{{ $size }}" @selected($isConfigured && $configuration['size'] === $size)>{{ $dimensions['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div data-configurator-body @unless ($isConfigured) hidden @endunless>
                <label class="portal-label" for="configuration_background">Achtergrond</label>
                <select class="portal-input" id="configuration_background" name="configuration[background]">
                    @foreach (UnitConfiguration::BACKGROUNDS as $background => $label)
                        <option value="{{ $background }}" @selected($configuration['background'] === $background)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="unit-configurator__colors" data-configurator-body @unless ($isConfigured) hidden @endunless>
                <label class="unit-configurator__color">
                    <input type="color" name="configuration[colors][cladding]" value="{{ $configuration['colors']['cladding'] }}">
                    Gevel
                </label>
                <label class="unit-configurator__color">
                    <input type="color" name="configuration[colors][frame]" value="{{ $configuration['colors']['frame'] }}">
                    Kozijnen en frame
                </label>
            </div>
        </div>

        <div class="unit-configurator__walls" data-configurator-body @unless ($isConfigured) hidden @endunless>
            @foreach (UnitConfiguration::WALLS as $wall => $wallLabel)
                <fieldset class="unit-configurator__wall">
                    <legend class="portal-label">{{ $wallLabel }}</legend>
                    <div class="unit-configurator__slots">
                        @for ($index = 0; $index < $maxSlots($wall); $index++)
                            @php($slot = $configuration['walls'][$wall][$index] ?? UnitConfiguration::EMPTY)
                            @php($inUse = $index < UnitConfiguration::slotCount($configuration['size'], $wall))
                            <button type="button" class="unit-slot unit-slot--{{ $slot }}" data-configurator-slot data-wall="{{ $wall }}" data-index="{{ $index }}"
                                    title="{{ $wallLabel }}, vak {{ $index + 1 }}" @unless ($inUse) hidden @endunless>
                                <i class="ph {{ $slotIcons[$slot] }}"></i>
                                <span>{{ UnitConfiguration::SLOTS[$slot] }}</span>
                                <input type="hidden" name="configuration[walls][{{ $wall }}][]" value="{{ $slot }}" @disabled(! $inUse)>
                            </button>
                        @endfor
                    </div>
                </fieldset>
            @endforeach
            <p class="portal-help">Gevels gezien van buitenaf, van links naar rechts. Elk vak is 1 meter breed.</p>
        </div>
    </div>

    <div class="unit-configurator__preview-panel" data-configurator-body @unless ($isConfigured) hidden @endunless>
        <div class="unit-configurator__views">
            <button type="button" class="unit-configurator__view is-active" data-configurator-view="2d" aria-pressed="true">
                <i class="ph ph-image"></i> 2D-tekening
            </button>
            <button type="button" class="unit-configurator__view" data-configurator-view="3d" aria-pressed="false">
                <i class="ph ph-cube"></i> 3D (prototype)
            </button>
        </div>
        <div class="unit-configurator__preview" data-configurator-preview>
            {!! app(UnitRenderer::class)->render($configuration) !!}
        </div>
        <div class="unit-configurator__viewer" data-configurator-viewer hidden></div>
        <p class="portal-help" data-configurator-viewer-help hidden>Sleep om de unit te draaien, scroll om in of uit te zoomen.</p>
        <label class="portal-checkbox">
            <input type="checkbox" name="photos_3d_enabled" value="1" data-configurator-photos @checked($usesPhotos)>
            Gebruik 3D-beelden als foto's op de website in plaats van de 2D-tekeningen
        </label>
        <p class="portal-help" role="status" data-configurator-photos-status></p>
        @foreach (array_keys(UnitRenderer::VIEWS) as $view)
            <input type="file" name="photos_3d[{{ $view }}]" accept="image/jpeg" hidden data-configurator-photo="{{ $view }}">
        @endforeach
    </div>
</div>
