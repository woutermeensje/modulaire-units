@php
    /**
     * The public unit composer as a section of another page (home page, huur- en koopagina's).
     *
     * @var array{configuration: array<string, mixed>} $composer
     */
@endphp
<section class="unit-composer unit-composer--section" id="unit-samensteller">
    <h2 class="mh-page-title">Stel je eigen unit samen</h2>
    <p class="unit-composer__intro">Kies de afmeting, het aantal units en lagen, de kleuren en waar ramen en deuren komen. Bewaar of deel je samenstelling met de link.</p>

    @include('partials.unit-composer', $composer)
</section>
