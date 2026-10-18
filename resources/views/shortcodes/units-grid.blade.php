<div class="mh-units-results">
    @if ($units->isEmpty())
        <div class="mh-units-gallery-empty">Geen units gevonden.</div>
    @else
        <div class="mh-units-grid">
            @foreach ($units as $unit)
                @include('shortcodes.unit-card', ['unit' => $unit])
            @endforeach
        </div>
    @endif
</div>
