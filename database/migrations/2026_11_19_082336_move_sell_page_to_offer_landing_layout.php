<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * The /verkopen/ page now uses the offer landing layout of /huren/ and /kopen/. Its hero gets the page's own intro
 * sentence instead of the generic rent-and-buy subtitle, and the old Elementor banner on top of the content (a second
 * h1 with that intro and two buttons without a link) is removed, the new layout has its own hero and buttons.
 * Each value only changes while it still holds the old value, so edits made in the CMS stay.
 */
return new class extends Migration
{
    private const PATH = 'verkopen';

    private const OLD_SUBTITLE = 'Neem contact op met ons team en laat je adviseren in de keuze voor de beste unit.';

    private const NEW_SUBTITLE = 'Heb je op dit moment een aantal modulaire units die je zou willen verkopen? Het team van ModulaireHuisvesting.nl is altijd geïnteresseerd in de aankoop van gebruikte modulaire units.';

    /**
     * The Elementor section with the old banner; the next top-level section holds the text that stays.
     */
    private const BANNER_SECTION = '/<section class="elementor-section elementor-top-section elementor-element elementor-element-3c270191\b.*?(?=<section class="elementor-section elementor-top-section)/s';

    public function up(): void
    {
        $page = Page::query()->where('path', self::PATH)->first();

        if ($page === null) {
            return;
        }

        $hero = $page->hero ?? [];

        if (($hero['subtitle'] ?? null) === self::OLD_SUBTITLE) {
            $hero['subtitle'] = self::NEW_SUBTITLE;
            $page->hero = $hero;
        }

        $page->content = $this->withoutBanner((string) $page->content);

        $page->save();
    }

    public function down(): void
    {
        //
    }

    private function withoutBanner(string $content): string
    {
        return (string) preg_replace(self::BANNER_SECTION, '', $content, 1);
    }
};
