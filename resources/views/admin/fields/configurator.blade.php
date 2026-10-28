@use('App\Support\UnitConfiguration')
@use('App\Support\UnitRenderer')
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
@endphp
<span class="portal-label">{{ $field->label }}</span>
<div class="unit-configurator" data-unit-configurator data-preview-url="{{ route('admin.units.preview') }}"
     data-slot-labels='@json(UnitConfiguration::SLOTS)' data-slot-icons='@json($slotIcons)'
     data-slot-counts='@json($slotCounts)'>
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

    <div class="unit-configurator__preview" data-configurator-preview data-configurator-body @unless ($isConfigured) hidden @endunless>
        {!! app(UnitRenderer::class)->render($configuration) !!}
    </div>
</div>
