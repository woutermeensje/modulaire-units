<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ComposedUnitRequest;
use App\Models\Unit;
use App\Services\UnitConfigurationImages;
use App\Support\UnitConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admins compose units for the landing pages in the account's unit composer and add them to the products (units).
 * A new unit starts as a draft; its status is changed in the list on /account/units/. Published units appear on /modulaire-units/.
 */
class ComposedUnitController extends Controller
{
    public function store(ComposedUnitRequest $request, UnitConfigurationImages $images): RedirectResponse
    {
        $validated = $request->validated();

        $unit = Unit::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'slug' => Unit::uniqueSlug($validated['title']),
            'content' => $validated['description'] ?? null,
            'price_rent_week' => $validated['price_rent_week'] ?? null,
            'configuration' => UnitConfiguration::normalize($validated['configuration']),
            'status' => 'draft',
            'sort' => 0,
        ]);

        $usesPhotos = $request->boolean('photos_3d_enabled');
        $images->sync($unit, $usesPhotos ? $request->file('photos_3d', []) : [], keepPhotos: $usesPhotos);

        return redirect()->route('my-used-listings.index')
            ->with('status', "\"{$unit->title}\" is als concept toegevoegd aan de producten. Kies Publiceren om hem op de website te zetten.");
    }

    public function updateStatus(Request $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(array_keys(Unit::STATUSES))]]);

        $unit->status = $validated['status'];
        if ($unit->status === 'published' && ($unit->published_at === null || $unit->published_at->isFuture())) {
            $unit->published_at = now();
        }
        $unit->save();

        return redirect()->route('my-used-listings.index')
            ->with('status', match ($unit->status) {
                'published' => "\"{$unit->title}\" staat nu op de website.",
                'draft' => "\"{$unit->title}\" is nu een concept.",
                default => "\"{$unit->title}\" staat nu offline.",
            });
    }
}
