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
            'units' => Unit::query()->published()->translated()->with('terms')->orderBy('sort')->latest('published_at')->get(),
            'archiveTitle' => 'Units',
            'seo' => Seo::route('Units', __('Bekijk alle modulaire units van ModulaireHuisvesting.nl: kopen, huren en gebruikt.'), 'units.index'),
        ]);
    }

    public function taxonomy(string $prefix, string $slug): View
    {
        $term = UnitTerm::query()
            ->where('taxonomy', UnitTerm::taxonomyForPrefix($prefix))
            ->whereLocalized('slug', $slug)
            ->firstOrFail();

        return view('units.index', [
            'units' => $term->units()->published()->translated()->with('terms')->orderBy('sort')->latest('published_at')->get(),
            'archiveTitle' => $term->name,
            'seo' => Seo::make(
                $term->name.' - '.__('Modulaire units'),
                $term->description ?: __('Bekijk alle modulaire units in de categorie :name van ModulaireHuisvesting.nl en vraag vrijblijvend informatie of een offerte aan.', ['name' => $term->name]),
                $term->url(),
            )->withAlternates(fn (): string => $term->url(), $term),
        ]);
    }

    public function show(string $slug): View
    {
        $unit = Unit::query()->whereLocalized('slug', $slug)->with('terms')->firstOrFail();

        abort_unless($unit->isPublished() || Auth::check(), 404);

        $relatedUnits = Unit::query()->published()->translated()->with('terms')
            ->whereKeyNot($unit->id)
            ->orderBy('sort')->latest('published_at')
            ->limit(4)
            ->get();

        return view('units.show', [
            'unit' => $unit,
            'content' => app(ShortcodeRenderer::class)->render($unit->content),
            'relatedUnits' => $relatedUnits,
            'viewer' => $unit->viewer(),
            'seo' => Seo::for($unit, $unit->summary(30) ?: __('Bekijk de :title van ModulaireHuisvesting.nl: kopen of huren, met transport en plaatsing. Vraag vrijblijvend informatie aan.', ['title' => $unit->title])),
        ]);
    }
}
