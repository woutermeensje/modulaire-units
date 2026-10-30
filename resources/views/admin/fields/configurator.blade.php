@use('App\Support\UnitConfiguration')
@use('App\Support\UnitRenderer')
@use('App\Support\FloorPlanRenderer')
@use('App\Services\UnitDesigner')
@use('App\Services\UnitConfigurationImages')
@php
    // In the composer test environment the configurator stands on its own: always configured, opened in 3D, no unit fields.
    $composer ??= false;
    // Without AI (the public composer) neither the description box nor the design endpoint is offered.
    $ai ??= true;
    $isConfigured = $composer || filled($value['size'] ?? null);
    $configuration = $isConfigured ? UnitConfiguration::normalize($value) : UnitConfiguration::defaults();
    $maxSlots = fn (string $wall): int => max(array_map(fn (string $size): int => UnitConfiguration::slotCount($size, $wall, UnitConfiguration::MAX_MODULES), array_keys(UnitConfiguration::SIZES)));
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
<div @class(['unit-configurator', 'unit-configurator--composer' => $composer]) data-unit-configurator data-preview-url="{{ $previewUrl ?? route('admin.units.preview') }}" @if ($ai) data-design-url="{{ $designUrl ?? route('admin.units.design') }}" @endif
     data-slot-labels='@json(UnitConfiguration::SLOTS)' data-slot-icons='@json($slotIcons)'
     data-slot-counts='@json($slotCounts)' data-backdrops='@json(UnitRenderer::BACKDROPS)'
     @if ($composer) data-composer data-share-param="{{ UnitConfiguration::SHARE_KEY }}" @endif>
    @if ($ai)
        <div class="unit-configurator__ai">
            <label class="portal-label" for="configuration_description"><i class="ph ph-sparkle"></i>
                {{ $composer ? 'Beschrijf wat je wilt aanpassen en laat Claude de unit bewerken' : 'Beschrijf de unit en laat Claude de configuratie invullen' }}
            </label>
            <textarea class="portal-textarea" id="configuration_description" rows="3" data-quill="off" data-configurator-description
                      placeholder="{{ $composer ? 'Bijv. maak de wanden blauw, zet een raam in de linkerzijde en koppel er een tweede unit achter' : 'Bijv. 6x3 kantoorunit in lichtgrijs, deur in het midden van de voorkant met aan beide kanten twee ramen, één raam in elke kopgevel, op een bouwterrein' }}"></textarea>
            <div class="unit-configurator__ai-actions">
                @unless ($composer)
                    <label class="portal-checkbox">
                        <input type="checkbox" data-configurator-texts checked>
                        Ook titel en meta description invullen (alleen lege velden)
                    </label>
                @endunless
                <button type="button" class="btn btn-primary" data-configurator-generate @disabled(! UnitDesigner::isConfigured())>
                    <i class="ph ph-sparkle"></i> {{ $composer ? 'Bewerken met AI' : 'Genereer met AI' }}
                </button>
            </div>
            <p class="portal-help" role="status" data-configurator-status>
                @unless (UnitDesigner::isConfigured())
                    AI is uitgeschakeld: stel ANTHROPIC_API_KEY in op de server.
                @endunless
            </p>
        </div>
    @endif

    <div class="unit-configurator__controls">
        <div class="unit-configurator__options">
            <div>
                <label class="portal-label" for="configuration_size">Afmeting</label>
                <select class="portal-input" id="configuration_size" name="configuration[size]" data-configurator-size data-search-select>
                    @unless ($composer)
                        <option value="">Geen configuratie</option>
                    @endunless
                    @foreach (UnitConfiguration::SIZES as $size => $dimensions)
                        <option value="{{ $size }}" @selected($isConfigured && $configuration['size'] === $size)>{{ $dimensions['label'] }}</option>
                    @endforeach
                </select>
            </div>
            @if ($composer && ! $ai)
                <div>
                    <label class="portal-label" for="configuration_usage">Type gebruik</label>
                    <select class="portal-input" id="configuration_usage" name="configuration[usage][]" multiple placeholder="Kies een of meer" data-configurator-usage data-search-select>
                        @foreach (UnitConfiguration::USAGES as $usage => $usageLabel)
                            <option value="{{ $usage }}" @selected(in_array($usage, $configuration['usage'] ?? [], true))>{{ $usageLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div data-configurator-body @unless ($isConfigured) hidden @endunless>
                <label class="portal-label" for="configuration_modules">Aantal units</label>
                <select class="portal-input" id="configuration_modules" name="configuration[modules]" data-configurator-modules data-search-select>
                    @for ($modules = 1; $modules <= UnitConfiguration::MAX_MODULES; $modules++)
                        <option value="{{ $modules }}" @selected($configuration['modules'] === $modules)>{{ $modules === 1 ? '1 unit' : $modules.' units gekoppeld' }}</option>
                    @endfor
                </select>
            </div>
            <div data-configurator-body @unless ($isConfigured) hidden @endunless>
                <label class="portal-label" for="configuration_floors">Lagen</label>
                <select class="portal-input" id="configuration_floors" name="configuration[floors]" data-configurator-floors data-search-select>
                    @for ($floors = 1; $floors <= UnitConfiguration::MAX_FLOORS; $floors++)
                        <option value="{{ $floors }}" @selected($configuration['floors'] === $floors)>{{ $floors === 1 ? '1 laag' : $floors.' lagen (gestapeld)' }}</option>
                    @endfor
                </select>
            </div>
            <div data-configurator-body @unless ($isConfigured) hidden @endunless>
                <label class="portal-label" for="configuration_background">Achtergrond</label>
                <select class="portal-input" id="configuration_background" name="configuration[background]" data-search-select>
                    @foreach (UnitConfiguration::BACKGROUNDS as $background => $label)
                        <option value="{{ $background }}" @selected($configuration['background'] === $background)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="unit-configurator__colors" data-configurator-body @unless ($isConfigured) hidden @endunless>
                @foreach (['cladding' => 'Wanden', 'frame' => 'Kozijnen en frame'] as $part => $partLabel)
                    <fieldset class="unit-configurator__palette">
                        <legend class="portal-label">{{ $partLabel }}</legend>
                        <div class="unit-configurator__swatches">
                            @foreach (UnitConfiguration::COLORS as $hex => $colorLabel)
                                <label class="unit-swatch">
                                    <input type="radio" name="configuration[colors][{{ $part }}]" value="{{ $hex }}" @checked($configuration['colors'][$part] === $hex)>
                                    <span class="unit-swatch__color" style="background: {{ $hex }}"></span>
                                    <span class="unit-swatch__label">{{ $colorLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </div>

        <div class="unit-configurator__levels" data-configurator-body @unless ($isConfigured) hidden @endunless>
            <ul class="unit-plan-legend" aria-label="Legenda">
                @foreach ([UnitConfiguration::EMPTY => 'Dicht', UnitConfiguration::WINDOW => 'Raam (180 cm)', UnitConfiguration::DOOR => 'Deur (87,5 cm)'] as $slot => $legendLabel)
                    <li class="unit-plan-legend__item">
                        <span class="unit-plan-legend__icon unit-slot--{{ $slot }}"><i class="ph {{ $slotIcons[$slot] }}"></i></span>
                        {{ $legendLabel }}
                    </li>
                @endforeach
                <li class="unit-plan-legend__hint">Klik op een vak om te wisselen.</li>
            </ul>
            @foreach (UnitConfiguration::LEVELS as $level => $levelLabel)
                @php($levelInUse = $loop->index < $configuration['floors'])
                <section class="unit-configurator__level" data-configurator-level="{{ $level }}" data-floor="{{ $loop->index }}" @unless ($levelInUse) hidden @endunless>
                    <h3 class="unit-configurator__level-title" data-configurator-level-title @if ($configuration['floors'] === 1) hidden @endif>{{ $levelLabel }}</h3>
                    @unless ($loop->first)
                        <div class="unit-configurator__stairs">
                            <input type="hidden" name="configuration[stairs]" value="0">
                            <label class="portal-checkbox">
                                <input type="checkbox" name="configuration[stairs]" value="1" data-configurator-stairs @checked($configuration['stairs'] || $configuration['floors'] === 1)>
                                Buitentrap naar de deuren op de verdieping
                            </label>
                            <p class="portal-help">Bij elke deur op de verdieping komt een bordes met een trap naar beneden. Zet dus minstens één deur op de verdieping.</p>
                        </div>
                    @endunless
                    {{-- Top view: the floor in the middle with every wall's slots along its own side. Slots stay in their
                         order as seen from outside the wall; the CSS turns each row or column to match the top view. --}}
                    <div class="unit-plan">
                        @foreach (['back', 'left', 'right', 'front'] as $wall)
                            @php($wallLabel = UnitConfiguration::WALLS[$wall])
                            <div class="unit-plan__wall unit-plan__wall--{{ $wall }}" role="group" aria-label="{{ $levelLabel }}, {{ mb_strtolower($wallLabel) }}">
                                @for ($index = 0; $index < $maxSlots($wall); $index++)
                                    @php($slot = $configuration[$level][$wall][$index] ?? UnitConfiguration::EMPTY)
                                    @php($inUse = $levelInUse && $index < UnitConfiguration::slotCount($configuration['size'], $wall, $configuration['modules']))
                                    <button type="button" class="unit-slot unit-slot--{{ $slot }}" data-configurator-slot data-level="{{ $level }}" data-wall="{{ $wall }}" data-index="{{ $index }}"
                                            title="{{ $levelLabel }}, {{ mb_strtolower($wallLabel) }}, vak {{ $index + 1 }}" @unless ($inUse) hidden @endunless>
                                        <i class="ph {{ $slotIcons[$slot] }}"></i>
                                        <span>{{ UnitConfiguration::SLOTS[$slot] }}</span>
                                        <input type="hidden" name="configuration[{{ $level }}][{{ $wall }}][]" value="{{ $slot }}" @disabled(! $inUse)>
                                    </button>
                                @endfor
                            </div>
                        @endforeach
                        <div class="unit-plan__floor">
                            @for ($module = UnitConfiguration::MAX_MODULES; $module >= 1; $module--)
                                <div class="unit-plan__module" data-configurator-module="{{ $module }}" @if ($module > $configuration['modules']) hidden @endif>
                                    <span data-configurator-module-label @if ($configuration['modules'] === 1) hidden @endif>Unit {{ $module }}</span>
                                </div>
                            @endfor
                            @foreach (UnitConfiguration::WALLS as $wall => $wallLabel)
                                <span class="unit-plan__name unit-plan__name--{{ $wall }}">{{ $wallLabel }}</span>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endforeach
            <p class="portal-help">Bovenaanzicht van de unit: klik op een vak langs een gevel om te wisselen tussen dicht, raam en deur. Elk vak is 1 meter; een raam van 1,80 m beslaat twee vakken. Gekoppelde units staan met de lange zijde tegen elkaar en vormen één open ruimte, met unit 1 aan de voorzijde. Met twee lagen stel je de verdieping apart in.</p>
        </div>
    </div>

    <div class="unit-configurator__preview-panel" data-configurator-body @unless ($isConfigured) hidden @endunless>
        @if ($quoteUrl ?? null)
            {{-- Shown once the visitor has made the unit their own (or opened a shared composition). --}}
            <div class="unit-configurator__quote" data-configurator-quote data-quote-url="{{ $quoteUrl }}" @if (! ($quoteReady ?? false)) hidden @endif>
                <a href="{{ route('quote.show') }}/" class="unit-configurator__quote-link" data-configurator-quote-link hidden>Bekijk offerte</a>
                <button type="button" class="unit-configurator__quote-button" data-configurator-quote-add>
                    <i class="ph ph-shopping-bag"></i> <span>Toevoegen aan offerte</span>
                </button>
            </div>
        @endif
        <div class="unit-configurator__views">
            <button type="button" @class(['unit-configurator__view', 'is-active' => ! $composer]) data-configurator-view="2d" aria-pressed="{{ $composer ? 'false' : 'true' }}">
                <i class="ph ph-ruler"></i> Plattegrond
            </button>
            <button type="button" @class(['unit-configurator__view', 'is-active' => $composer]) data-configurator-view="3d" aria-pressed="{{ $composer ? 'true' : 'false' }}">
                <i class="ph ph-cube"></i> 3D (prototype)
            </button>
        </div>
        <div class="unit-configurator__preview" data-configurator-preview @if ($composer) hidden @endif>
            {!! app(FloorPlanRenderer::class)->render($configuration) !!}
        </div>
        <div class="unit-configurator__viewer" data-configurator-viewer @unless ($composer) hidden @endunless></div>
        <p class="portal-help" data-configurator-viewer-help @unless ($composer) hidden @endunless>Sleep om de unit te draaien, scroll om in of uit te zoomen.</p>
        @if ($composer)
            <div class="unit-configurator__share">
                <input class="portal-input" readonly value="{{ request()->fullUrl() }}" aria-label="Link naar deze samenstelling" data-configurator-share-url>
                <button type="button" class="btn btn-secondary" data-configurator-share-copy><i class="ph ph-link"></i> Kopieer link</button>
            </div>
        @else
            <label class="portal-checkbox">
                <input type="checkbox" name="photos_3d_enabled" value="1" data-configurator-photos @checked($usesPhotos)>
                Gebruik 3D-beelden als foto's op de website in plaats van de 2D-tekeningen
            </label>
            <p class="portal-help" role="status" data-configurator-photos-status></p>
            @foreach (array_keys(UnitRenderer::VIEWS) as $view)
                <input type="file" name="photos_3d[{{ $view }}]" accept="image/jpeg" hidden data-configurator-photo="{{ $view }}">
            @endforeach
        @endif
    </div>
</div>
