<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\UnitTerm;
use App\Services\Content\ShortcodeRenderer;
use App\Support\Seo;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        return view('units.index', [
            'units' => Unit::query()->published()->with('terms')->orderBy('sort')->latest('published_at')->get(),
            'archiveTitle' => 'Units',
            'seo' => Seo::make('Units', 'Bekijk alle modulaire units van ModulaireHuisvesting.nl: kopen, huren en gebruikt.', route('units.index', [], false)),
        ]);
    }

    public function taxonomy(string $prefix, string $slug): View
    {
        $term = UnitTerm::query()
            ->where('taxonomy', UnitTerm::taxonomyForPrefix($prefix))
            ->where('slug', $slug)
            ->firstOrFail();

        return view('units.index', [
            'units' => $term->units()->published()->with('terms')->orderBy('sort')->latest('published_at')->get(),
            'archiveTitle' => $term->name,
            'seo' => Seo::make($term->name.' Archieven', $term->description, $term->url()),
        ]);
    }

    public function show(string $slug): View
    {
        $unit = Unit::query()->where('slug', $slug)->with('terms')->firstOrFail();

        abort_unless($unit->isPublished() || Auth::check(), 404);

        $relatedUnits = Unit::query()->published()->with('terms')
            ->whereKeyNot($unit->id)
            ->orderBy('sort')->latest('published_at')
            ->limit(4)
            ->get();

        return view('units.show', [
            'unit' => $unit,
            'content' => app(ShortcodeRenderer::class)->render($unit->content),
            'relatedUnits' => $relatedUnits,
            'seo' => Seo::for($unit, $unit->summary(30)),
        ]);
    }
}
