<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;

/**
 * Renames "Aanbod" in the main menu to "Units" and adds the public unit composer to its dropdown, after the
 * existing items (Kopen, Huren, Gebruikt).
 */
return new class extends Migration
{
    private const COMPOSER_URL = '/unit-samensteller/';

    public function up(): void
    {
        $parent = MenuItem::query()->where('menu', 'main')->whereNull('parent_id')->whereIn('label', ['Aanbod', 'Units'])->first();

        if ($parent === null) {
            return;
        }

        $parent->update(['label' => 'Units']);

        if (! $parent->children()->where('url', self::COMPOSER_URL)->exists()) {
            MenuItem::query()->create([
                'menu' => 'main',
                'parent_id' => $parent->id,
                'label' => 'Units samenstellen',
                'url' => self::COMPOSER_URL,
                'sort' => (int) $parent->children()->max('sort') + 1,
            ]);
        }
    }

    public function down(): void
    {
        $parent = MenuItem::query()->where('menu', 'main')->whereNull('parent_id')->where('label', 'Units')->first();

        if ($parent === null) {
            return;
        }

        $parent->children()->where('url', self::COMPOSER_URL)->delete();
        $parent->update(['label' => 'Aanbod']);
    }
};
