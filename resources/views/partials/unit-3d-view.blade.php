@php
    /**
     * The 3D prototype of a configured unit, with a switch between floor plan, 3D and a look inside.
     * Shown on the unit cards of /modulaire-units/ and on the unit detail page; resources/js/site/unit-card-viewer.js
     * starts the viewer once the block comes into view. Until then, and without WebGL, the poster image is shown.
     *
     * @var \App\Models\Unit $unit
     * @var array{configuration: array<string, mixed>, dimensions: array{length: int, depth: int}, backdrops: array<string, array<string, string>>} $viewer
     * @var string|null $poster  Media path of the image shown until the 3D view is ready.
     * @var string $posterVariant  Media variant of the poster image.
     */
    $poster ??= null;
    $posterVariant ??= 'medium';
@endphp
<div class="mh-unit-3d" data-unit-card>
    <div class="mh-unit-3d__pane mh-unit-3d__plan" data-unit-card-plan role="img" aria-label="Plattegrond van {{ $unit->title }}" hidden>
        {!! app(\App\Support\FloorPlanRenderer::class)->render($viewer['configuration']) !!}
    </div>
    <div class="mh-unit-3d__pane mh-unit-3d__viewer" data-unit-card-viewer='@json($viewer)' role="img" aria-label="Draaibare 3D-weergave van {{ $unit->title }}">
        @if ($poster)
            <img src="{{ \App\Support\SiteUrl::media(\App\Models\Media::variant($poster, $posterVariant)) }}" alt="" width="600" height="400" loading="lazy" data-unit-card-viewer-poster>
        @endif
        <span class="mh-unit-3d__hint" aria-hidden="true">Sleep om te draaien</span>
    </div>
    <div class="mh-unit-3d__views" role="group" aria-label="Weergave van {{ $unit->title }}">
        <button type="button" class="mh-unit-3d__view" data-unit-card-view="2d" aria-pressed="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2"/><path d="m11.5 9.5 2-2"/><path d="m8.5 6.5 2-2"/><path d="m17.5 15.5 2-2"/></svg>
            Plattegrond
        </button>
        <button type="button" class="mh-unit-3d__view is-active" data-unit-card-view="3d" aria-pressed="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            3D
        </button>
        <button type="button" class="mh-unit-3d__view" data-unit-card-view="interior" aria-pressed="false">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 9V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v3"/><path d="M2 16a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v1.5a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5V11a2 2 0 0 0-4 0z"/><path d="M4 18v2"/><path d="M20 18v2"/></svg>
            Binnenkant
        </button>
    </div>
</div>
