<?php

use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes the "Bekijk het overzicht - Kopen/Huren" blocks (an Elementor section with a [subpage_grid] of the
 * /kopen/ or /huren/ subpages) from the page content: the unit composer replaces them. /aanbod/ held nothing
 * else, so it now redirects to /modulaire-units/.
 */
return new class extends Migration
{
    private const SECTION_START = '<section class="elementor-section elementor-top-section';

    public function up(): void
    {
        Page::query()->where('content', 'like', '%[subpage_grid%')->each(function (Page $page): void {
            $page->update(['content' => $this->withoutGridSections((string) $page->content)]);
        });

        $overview = Page::query()->where('path', 'aanbod')->first();

        if ($overview !== null && trim(strip_tags((string) $overview->content)) === '') {
            Redirect::query()->firstOrCreate(
                ['from_path' => Redirect::normalize('aanbod')],
                ['to_path' => '/modulaire-units/', 'status_code' => 301],
            );
        }
    }

    public function down(): void
    {
        Redirect::query()->where('from_path', Redirect::normalize('aanbod'))->where('to_path', '/modulaire-units/')->delete();
    }

    /**
     * Drops every top-level Elementor section that contains a [subpage_grid]; inner sections close before their
     * top-level section, so the last </section> of a chunk is the one that closes it.
     */
    private function withoutGridSections(string $content): string
    {
        $chunks = preg_split('/(?='.preg_quote(self::SECTION_START, '/').')/', $content);

        return implode('', array_map(function (string $chunk): string {
            if (! str_starts_with($chunk, self::SECTION_START) || ! str_contains($chunk, '[subpage_grid')) {
                return $chunk;
            }

            return substr($chunk, strrpos($chunk, '</section>') + strlen('</section>'));
        }, $chunks));
    }
};
