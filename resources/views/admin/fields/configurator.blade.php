@use('App\Support\SiteUrl')
@use('App\Support\UnitConfiguration')
@use('App\Support\UnitRenderer')
@use('App\Support\FloorPlanRenderer')
@use('App\Services\UnitDesigner')
@use('App\Services\UnitConfigurationImages')
@php
    // In the composer test environment the configurator stands on its own: always configured, opened in 3D, no unit fields.
    $composer ??= false;
    // AI is only offered in the composer test environment in the admin (Admin\UnitComposerController passes it).
    // Without AI neither the description box nor the design endpoint is offered.
    $ai ??= false;
    // Admins add a composition from their account to the products instead of to the quote; see Portal\UnitComposerController.
    $storeUrl ??= null;
    // The quote composer (account/offerte-samenstellen, admins only) also offers what was taken out of the customer's
    // configurator: the use of the unit, connections, furniture and a step; see Portal\QuoteComposerController.
    $quoteExtras ??= false;
    // The public composer (/unit-samensteller/ and the home page) leaves the interior out for now; see partials/unit-composer.
    $offersInterior ??= true;
    $isConfigured = $composer || filled($value['size'] ?? null);
    $configuration = $isConfigured ? UnitConfiguration::normalize($value) : UnitConfiguration::defaults();
    if (! $offersInterior) {
        // Without the choice an older share link must not keep an interior that can't be turned off.
        unset($configuration['interior'], $configuration['tentative']);
    }
    $maxSlots = fn (string $wall): int => max(array_map(fn (string $size): int => UnitConfiguration::slotCount($size, $wall, UnitConfiguration::MAX_MODULES), array_keys(UnitConfiguration::SIZES)));
    $slotCounts = [];
    foreach (array_keys(UnitConfiguration::SIZES) as $size) {
        foreach (array_keys(UnitConfiguration::WALLS) as $wall) {
            $slotCounts[$size][$wall] = UnitConfiguration::slotCount($size, $wall);
        }
    }
    $slotIcons = [UnitConfiguration::EMPTY => 'ph-square', UnitConfiguration::WINDOW => 'ph-app-window', UnitConfiguration::SINGLE_WINDOW => 'ph-browser', UnitConfiguration::DOOR => 'ph-door', UnitConfiguration::HATCH => 'ph-storefront'];
    $interiorOptions = [
        'airco' => ['icon' => 'ph-snowflake', 'help' => __('Airco-unit aan de wand, voor koelen en verwarmen')],
        'heater' => ['icon' => 'ph-thermometer-hot', 'help' => __('Elektrische verwarming laag aan de wand')],
        'toilet' => ['icon' => 'ph-toilet', 'help' => __('Toiletruimte met fonteintje')],
        'kitchen' => ['icon' => 'ph-cooking-pot', 'help' => __('Keukenblok met spoelbak en kookplaat')],
    ];
    // The interior options per category, in the order they are shown.
    $interiorGroups = $offersInterior ? ['Klimaat & verwarming' => ['airco', 'heater'], 'Sanitair & keuken' => ['toilet', 'kitchen']] : [];
    $countIcons = [
        'desk' => 'ph-desk', 'chair' => 'ph-chair', 'counter' => 'ph-storefront', 'worktop' => 'ph-rectangle', 'cabinet' => 'ph-lockers',
        'socket' => 'ph-plug', 'network' => 'ph-network',
    ];
    $countGroups = $quoteExtras ? [
        'Elektra & netwerk' => ['options' => UnitConfiguration::CONNECTIONS, 'key' => 'connections', 'max' => UnitConfiguration::MAX_CONNECTIONS],
        'Meubilair' => ['options' => UnitConfiguration::FURNITURE, 'key' => 'furniture', 'max' => UnitConfiguration::MAX_FURNITURE],
    ] : [];
    $chosen = [...($configuration['interior'] ?? []), ...array_keys($configuration['furniture'] ?? [])];
    // What the floor of the top view shows for every interior item, so it can be dragged into place there.
    $itemIcons = array_map(fn (array $option): string => $option['icon'], $interiorOptions) + $countIcons;
    $itemLabels = array_map(fn (string $itemLabel): string => __($itemLabel), UnitConfiguration::INTERIOR + UnitConfiguration::FURNITURE + UnitConfiguration::CONNECTIONS);
    // Furniture is only offered in the quote composer, so only there it can be marked as uncertain.
    $tentativeOptions = UnitConfiguration::INTERIOR + ($quoteExtras ? UnitConfiguration::FURNITURE : []);
    $usesPhotos = UnitConfigurationImages::usesPhotos($record?->configuration_images);
    $slotLabels = array_map(fn (string $slotLabel): string => __($slotLabel), UnitConfiguration::SLOTS);
@endphp
<span class="portal-label">{{ __($field->label) }}</span>
<div @class(['unit-configurator', 'unit-configurator--composer' => $composer]) data-unit-configurator data-preview-url="{{ $previewUrl ?? route('admin.units.preview') }}" @if ($ai) data-design-url="{{ $designUrl ?? route('admin.units.design') }}" @endif
     data-slot-labels='@json($slotLabels)' data-slot-icons='@json($slotIcons)'
     data-slot-counts='@json($slotCounts)' data-backdrops='@json(UnitRenderer::BACKDROPS)'
     data-item-icons='@json($itemIcons)' data-item-labels='@json($itemLabels)'
     @if ($composer) data-composer data-share-param="{{ UnitConfiguration::SHARE_KEY }}" @endif>
    @if ($ai)
        <div class="unit-configurator__ai">
            <label class="portal-label" for="configuration_description"><i class="ph ph-sparkle"></i>
                {{ $composer ? __('Beschrijf wat je wilt aanpassen en laat Claude de unit bewerken') : __('Beschrijf de unit en laat Claude de configuratie invullen') }}
            </label>
            <textarea class="portal-textarea" id="configuration_description" rows="3" data-quill="off" data-configurator-description
                      placeholder="{{ $composer ? __('Bijv. maak de wanden blauw, zet een raam in de linkerzijde en koppel er een tweede unit achter') : __('Bijv. 6x3 kantoorunit in lichtgrijs, deur in het midden van de voorkant met aan beide kanten twee ramen, één raam in elke kopgevel, op een bouwterrein') }}"></textarea>
            <div class="unit-configurator__ai-actions">
                @unless ($composer)
                    <label class="portal-checkbox">
                        <input type="checkbox" data-configurator-texts checked>
                        {{ __('Ook titel en meta description invullen (alleen lege velden)') }}
                    </label>
                @endunless
                <button type="button" class="btn btn-primary" data-configurator-generate @disabled(! UnitDesigner::isConfigured())>
                    <i class="ph ph-sparkle"></i> {{ $composer ? __('Bewerken met AI') : __('Genereer met AI') }}
                </button>
            </div>
            <p class="portal-help" role="status" data-configurator-status>
                @unless (UnitDesigner::isConfigured())
                    {{ __('AI is uitgeschakeld: stel ANTHROPIC_API_KEY in op de server.') }}
                @endunless
            </p>
        </div>
    @endif

    <div class="unit-configurator__controls">
        <div class="unit-configurator__options">
            <div>
                <label class="portal-label" for="configuration_size">{{ __('Afmeting') }}</label>
                <select class="portal-input" id="configuration_size" name="configuration[size]" data-configurator-size data-search-select>
                    @unless ($composer)
                        <option value="">{{ __('Geen configuratie') }}</option>
                    @endunless
                    @foreach (UnitConfiguration::SIZES as $size => $dimensions)
                        <option value="{{ $size }}" @selected($isConfigured && $configuration['size'] === $size)>{{ __($dimensions['label']) }}</option>
                    @endforeach
                </select>
            </div>
            @if ($quoteExtras)
                <div class="unit-configurator__usage">
                    <label class="portal-label" for="configuration_usage">{{ __('Type gebruik') }}</label>
                    <select class="portal-input" id="configuration_usage" name="configuration[usage][]" multiple placeholder="{{ __('Wat voor gebruik?') }}" data-configurator-usage data-search-select>
                        @foreach (UnitConfiguration::USAGES as $usage => $usageLabel)
                            <option value="{{ $usage }}" @selected(in_array($usage, $configuration['usage'] ?? [], true))>{{ __($usageLabel) }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div data-configurator-body @unless ($isConfigured) hidden @endunless>
                <label class="portal-label" for="configuration_modules">{{ __('Aantal units') }}</label>
                <select class="portal-input" id="configuration_modules" name="configuration[modules]" data-configurator-modules data-search-select>
                    @for ($modules = 1; $modules <= UnitConfiguration::MAX_MODULES; $modules++)
                        <option value="{{ $modules }}" @selected($configuration['modules'] === $modules)>{{ trans_choice('{1} 1 unit|[2,*] :count units gekoppeld', $modules) }}</option>
                    @endfor
                </select>
            </div>
            <div data-configurator-body @unless ($isConfigured) hidden @endunless>
                <label class="portal-label" for="configuration_floors">{{ __('Lagen') }}</label>
                <select class="portal-input" id="configuration_floors" name="configuration[floors]" data-configurator-floors data-search-select>
                    @for ($floors = 1; $floors <= UnitConfiguration::MAX_FLOORS; $floors++)
                        <option value="{{ $floors }}" @selected($configuration['floors'] === $floors)>{{ trans_choice('{1} 1 laag|[2,*] :count lagen (gestapeld)', $floors) }}</option>
                    @endfor
                </select>
            </div>
            {{-- Not offered as a choice: units stand on an industrial estate, unless the AI or an older configuration says otherwise. --}}
            <input type="hidden" name="configuration[background]" value="{{ $configuration['background'] }}">
            <div class="unit-configurator__colors" data-configurator-body @unless ($isConfigured) hidden @endunless>
                {{-- Only the walls have a choice: the frame has their colour and window and door frames are always white (see UnitConfiguration). --}}
                @foreach (['cladding' => 'Wanden'] as $part => $partLabel)
                    <fieldset class="unit-configurator__palette">
                        <legend class="portal-label">{{ __($partLabel) }}</legend>
                        <div class="unit-configurator__swatches">
                            @foreach (UnitConfiguration::COLORS as $hex => $colorLabel)
                                <label class="unit-swatch">
                                    <input type="radio" name="configuration[colors][{{ $part }}]" value="{{ $hex }}" @checked($configuration['colors'][$part] === $hex)>
                                    <span class="unit-swatch__color" style="background: {{ $hex }}"></span>
                                    <span class="unit-swatch__label">{{ __($colorLabel) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
            @foreach ($interiorGroups as $groupLabel => $items)
                <fieldset class="unit-configurator__palette unit-configurator__interior" data-configurator-body @unless ($isConfigured) hidden @endunless>
                    <legend class="portal-label">{{ __($groupLabel) }}</legend>
                    <div class="unit-configurator__swatches">
                        @foreach ($items as $item)
                            <label class="unit-swatch" title="{{ $interiorOptions[$item]['help'] }}">
                                <input type="checkbox" name="configuration[interior][]" value="{{ $item }}" @checked(in_array($item, $configuration['interior'] ?? [], true))>
                                <span class="unit-swatch__icon"><i class="ph {{ $interiorOptions[$item]['icon'] }}"></i></span>
                                <span class="unit-swatch__label">{{ __(UnitConfiguration::INTERIOR[$item]) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @if ($loop->first)
                        <p class="portal-help">{{ __('Een airco koelt en verwarmt; elektrische verwarming staat laag aan de wand. Van allebei komt er één per unit, ook op de verdieping. Te combineren.') }}</p>
                    @else
                        <p class="portal-help">{{ __('Het toilet en het keukentje staan op de begane grond, op een plek zonder deur.') }}</p>
                    @endif
                </fieldset>
            @endforeach
            @foreach ($countGroups as $groupLabel => $group)
                <fieldset class="unit-configurator__palette unit-configurator__interior" data-configurator-body @unless ($isConfigured) hidden @endunless>
                    <legend class="portal-label">{{ __($groupLabel) }}</legend>
                    <div class="unit-configurator__counts">
                        @foreach ($group['options'] as $item => $itemLabel)
                            @php($count = $configuration[$group['key']][$item] ?? 0)
                            <div @class(['unit-count', 'is-chosen' => $count > 0]) data-configurator-count>
                                <label class="unit-count__label" for="configuration_{{ $group['key'] }}_{{ $item }}"><i class="ph {{ $countIcons[$item] }}"></i> {{ __($itemLabel) }}</label>
                                <div class="unit-count__stepper">
                                    <button type="button" class="unit-count__button" data-count-step="-1" aria-label="{{ __('Minder') }}"><i class="ph ph-minus"></i></button>
                                    <input class="unit-count__input" type="number" inputmode="numeric" id="configuration_{{ $group['key'] }}_{{ $item }}" name="configuration[{{ $group['key'] }}][{{ $item }}]" value="{{ $count }}" min="0" max="{{ $group['max'] }}" data-configurator-amount>
                                    <button type="button" class="unit-count__button" data-count-step="1" aria-label="{{ __('Meer') }}"><i class="ph ph-plus"></i></button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="portal-help">{{ $group['key'] === 'connections'
                        ? __('Verlichting zit er altijd in. Wandcontactdozen worden over de wanden verdeeld, netwerkaansluitingen komen ernaast.')
                        : __('Een uitgiftebalie komt bij een uitgifteluik of raam, een bureau bij een raam en een stoel bij een bureau of balie. Meubilair staat op de begane grond.') }}</p>
                </fieldset>
            @endforeach
            @if ($quoteExtras)
                <fieldset class="unit-configurator__palette unit-configurator__interior" data-configurator-body @unless ($isConfigured) hidden @endunless>
                    <legend class="portal-label">{{ __('Toegang') }}</legend>
                    <label class="portal-checkbox">
                        <input type="checkbox" name="configuration[steps]" value="1" data-configurator-steps @checked($configuration['steps'] ?? false)>
                        {{ __('Opstapje voor de deuren op de begane grond') }}
                    </label>
                </fieldset>
            @endif
            @if ($offersInterior)
            <fieldset class="unit-configurator__palette unit-configurator__interior" data-configurator-body data-configurator-tentative @unless ($isConfigured && $chosen) hidden @endunless>
                <legend class="portal-label">{{ __('Nog niet zeker') }}</legend>
                <div class="unit-configurator__swatches">
                    @foreach ($tentativeOptions as $item => $itemLabel)
                        @php($isChosen = in_array($item, $chosen, true))
                        <label class="unit-swatch unit-swatch--small" data-tentative-for="{{ $item }}" @unless ($isChosen) hidden @endunless>
                            <input type="checkbox" name="configuration[tentative][]" value="{{ $item }}" @checked(in_array($item, $configuration['tentative'] ?? [], true)) @disabled(! $isChosen)>
                            <span class="unit-swatch__icon"><i class="ph ph-question"></i></span>
                            <span class="unit-swatch__label">{{ __($itemLabel) }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="portal-help">{{ __('Vink aan wat je wel wilt meenemen in de offerte, maar nog niet zeker weet. Het staat dan als optie op de plattegrond.') }}</p>
            </fieldset>
            @endif
        </div>

        <div class="unit-configurator__levels" data-configurator-body @unless ($isConfigured) hidden @endunless>
            <div class="unit-configurator__plan-intro">
                <h3 class="unit-configurator__plan-title">{{ __('Ramen en deuren plaatsen') }}</h3>
                <p class="portal-help">{{ __('Klik op een vak om te wisselen. Sleep wat binnen staat naar de plek die je wilt.') }}</p>
            </div>
            <ul class="unit-plan-legend" aria-label="{{ __('Legenda') }}">
                @foreach ([UnitConfiguration::EMPTY => 'Dicht', UnitConfiguration::WINDOW => 'Raam (180 cm)', UnitConfiguration::SINGLE_WINDOW => 'Enkel raam (94,5 cm)', UnitConfiguration::DOOR => 'Deur (87,5 cm)', UnitConfiguration::HATCH => 'Uitgifteluik (80 cm, wind- en waterdicht)'] as $slot => $legendLabel)
                    <li class="unit-plan-legend__item">
                        <span class="unit-plan-legend__icon unit-slot--{{ $slot }}"><i class="ph {{ $slotIcons[$slot] }}"></i></span>
                        {{ __($legendLabel) }}
                    </li>
                @endforeach
            </ul>
            @foreach (UnitConfiguration::LEVELS as $level => $levelLabel)
                @php($levelInUse = $loop->index < $configuration['floors'])
                <section class="unit-configurator__level" data-configurator-level="{{ $level }}" data-floor="{{ $loop->index }}" @unless ($levelInUse) hidden @endunless>
                    <h3 class="unit-configurator__level-title" data-configurator-level-title @if ($configuration['floors'] === 1) hidden @endif>{{ __($levelLabel) }}</h3>
                    @unless ($loop->first)
                        <div class="unit-configurator__stairs">
                            <input type="hidden" name="configuration[stairs]" value="0">
                            <label class="portal-checkbox">
                                <input type="checkbox" name="configuration[stairs]" value="1" data-configurator-stairs @checked($configuration['stairs'] || $configuration['floors'] === 1)>
                                {{ __('Buitentrap naar de deuren op de verdieping') }}
                            </label>
                            <p class="portal-help">{{ __('Bij elke deur op de verdieping komt een bordes met een trap naar beneden. Zet dus minstens één deur op de verdieping.') }}</p>
                        </div>
                    @endunless
                    {{-- Top view: the floor in the middle with every wall's slots along its own side. Slots stay in their
                         order as seen from outside the wall; the CSS turns each row or column to match the top view. --}}
                    <div class="unit-plan">
                        @foreach (['back', 'left', 'right', 'front'] as $wall)
                            @php($wallLabel = UnitConfiguration::WALLS[$wall])
                            <div class="unit-plan__wall unit-plan__wall--{{ $wall }}" role="group" aria-label="{{ __($levelLabel) }}, {{ mb_strtolower(__($wallLabel)) }}">
                                @for ($index = 0; $index < $maxSlots($wall); $index++)
                                    @php($slot = $configuration[$level][$wall][$index] ?? UnitConfiguration::EMPTY)
                                    @php($inUse = $levelInUse && $index < UnitConfiguration::slotCount($configuration['size'], $wall, $configuration['modules']))
                                    <button type="button" class="unit-slot unit-slot--{{ $slot }}" data-configurator-slot data-level="{{ $level }}" data-wall="{{ $wall }}" data-index="{{ $index }}"
                                            title="{{ __(':level, :wall, vak :number', ['level' => __($levelLabel), 'wall' => mb_strtolower(__($wallLabel)), 'number' => $index + 1]) }}" @unless ($inUse) hidden @endunless>
                                        <i class="ph {{ $slotIcons[$slot] }}"></i>
                                        <span>{{ $slotLabels[$slot] }}</span>
                                        <input type="hidden" name="configuration[{{ $level }}][{{ $wall }}][]" value="{{ $slot }}" @disabled(! $inUse)>
                                    </button>
                                @endfor
                            </div>
                        @endforeach
                        <div class="unit-plan__floor">
                            @for ($module = UnitConfiguration::MAX_MODULES; $module >= 1; $module--)
                                <div class="unit-plan__module" data-configurator-module="{{ $module }}" @if ($module > $configuration['modules']) hidden @endif>
                                    <span data-configurator-module-label @if ($configuration['modules'] === 1) hidden @endif>{{ __('Unit :number', ['number' => $module]) }}</span>
                                </div>
                            @endfor
                            @foreach (UnitConfiguration::WALLS as $wall => $wallLabel)
                                <span class="unit-plan__name unit-plan__name--{{ $wall }}">{{ __($wallLabel) }}</span>
                            @endforeach
                            {{-- Filled by the JavaScript with the interior of this layer, which can be dragged into place. --}}
                            <div class="unit-plan__items" data-configurator-items data-level="{{ $level }}"></div>
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    <div class="unit-configurator__preview-panel" data-configurator-body @unless ($isConfigured) hidden @endunless>
        <div class="unit-configurator__stage">
            @if ($storeUrl)
                <div class="unit-configurator__quote">
                    <button type="submit" class="unit-configurator__quote-button">
                        <i class="ph ph-plus-circle"></i> <span>{{ __('Unit toevoegen aan producten') }}</span>
                    </button>
                </div>
            @elseif ($quoteUrl ?? null)
                {{-- Shown right away, so the starting unit can be asked for as it is. --}}
                <div class="unit-configurator__quote" data-configurator-quote data-quote-url="{{ $quoteUrl }}">
                    <a href="{{ SiteUrl::route('quote.show') }}" class="unit-configurator__quote-link" data-configurator-quote-link hidden>{{ __('Bekijk offerte') }}</a>
                    <button type="button" class="unit-configurator__quote-button" data-configurator-quote-add data-label="{{ __('Toevoegen aan offerte') }}">
                        <i class="ph ph-shopping-bag"></i> <span>{{ __('Toevoegen aan offerte') }}</span>
                    </button>
                </div>
            @endif
            <div class="unit-configurator__views">
                <button type="button" @class(['unit-configurator__view', 'is-active' => ! $composer]) data-configurator-view="2d" aria-pressed="{{ $composer ? 'false' : 'true' }}">
                    <i class="ph ph-ruler"></i> {{ __('Plattegrond') }}
                </button>
                <button type="button" @class(['unit-configurator__view', 'is-active' => $composer]) data-configurator-view="3d" aria-pressed="{{ $composer ? 'true' : 'false' }}">
                    <i class="ph ph-cube"></i> {{ __('3D (prototype)') }}
                </button>
                <button type="button" class="unit-configurator__view" data-configurator-view="interior" aria-pressed="false">
                    <i class="ph ph-armchair"></i> {{ __('Binnenkant') }}
                </button>
            </div>
            <div class="unit-configurator__preview" data-configurator-preview @if ($composer) hidden @endif>
                {!! app(FloorPlanRenderer::class)->render($configuration) !!}
            </div>
            <div data-configurator-positions hidden>
                @foreach ($configuration['positions'] ?? [] as $id => $position)
                    <input type="hidden" name="configuration[positions][{{ $id }}][x]" value="{{ $position['x'] }}" data-position="{{ $id }}">
                    <input type="hidden" name="configuration[positions][{{ $id }}][y]" value="{{ $position['y'] }}" data-position="{{ $id }}">
                @endforeach
            </div>
            <div class="unit-configurator__viewer" data-configurator-viewer @unless ($composer) hidden @endunless></div>
            <p class="unit-configurator__viewer-note" data-configurator-interior-note hidden>{{ __('Je ziet de binnenkant van de begane grond; de verdieping is hier weggelaten.') }}</p>
        </div>
        <p class="portal-help" data-configurator-viewer-help @unless ($composer) hidden @endunless>{{ __('Sleep om de unit te draaien, scroll om in of uit te zoomen.') }}</p>
        <div class="unit-configurator__plan-help" data-configurator-plan-help @if ($composer) hidden @endif>
            <p class="portal-help">{{ __('Sleep een item op de plattegrond om het te verplaatsen: wat aan de wand hangt schuift naar de dichtstbijzijnde wand, een bureau of stoel blijft staan waar je hem neerzet.') }}</p>
            <button type="button" class="btn btn-secondary" data-configurator-positions-reset @if (empty($configuration['positions'])) hidden @endif>
                <i class="ph ph-arrow-counter-clockwise"></i> {{ __('Automatische indeling') }}
            </button>
        </div>
        @if ($composer && ! $storeUrl)
            <div class="unit-configurator__share">
                <input class="portal-input" readonly value="{{ request()->fullUrl() }}" aria-label="{{ __('Link naar deze samenstelling') }}" data-configurator-share-url>
                <button type="button" class="btn btn-secondary" data-configurator-share-copy><i class="ph ph-link"></i> {{ __('Kopieer link') }}</button>
            </div>
        @else
            <label class="portal-checkbox">
                <input type="checkbox" name="photos_3d_enabled" value="1" data-configurator-photos @checked($usesPhotos)>
                {{ __("Gebruik 3D-beelden als foto's op de website in plaats van de 2D-tekeningen") }}
            </label>
            <p class="portal-help" role="status" data-configurator-photos-status></p>
            @foreach (array_keys(UnitRenderer::VIEWS) as $view)
                <input type="file" name="photos_3d[{{ $view }}]" accept="image/jpeg" hidden data-configurator-photo="{{ $view }}">
            @endforeach
        @endif
    </div>
    @if (! $storeUrl && ($quoteUrl ?? null))
        {{-- Appears at the bottom of the page as soon as the unit is edited; see resetQuote() in unit-configurator.js. --}}
        <div class="unit-quote-bar" data-configurator-quote-bar hidden>
            <a href="{{ SiteUrl::route('quote.show') }}" class="unit-quote-bar__link" data-configurator-quote-link hidden>{{ __('Bekijk offerte') }}</a>
            <button type="button" class="unit-quote-bar__button" data-configurator-quote-add data-label="{{ __('Unit toevoegen aan offerte') }}">
                <i class="ph ph-shopping-bag"></i> <span>{{ __('Unit toevoegen aan offerte') }}</span>
            </button>
        </div>
    @endif
</div>
