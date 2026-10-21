<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * Replaces the loose "Onze Werkwijze" text on /over-ons/ with the [werkwijze] block, placed in its own section
 * below the introduction.
 */
return new class extends Migration
{
    public function up(): void
    {
        $page = Page::query()->where('path', 'over-ons')->first();

        if (! $page || str_contains((string) $page->content, '[werkwijze]')) {
            return;
        }

        $content = (string) preg_replace(
            '/<section class="elementor-section elementor-inner-section[^"]*"[^>]*>(?:(?!<section).)*?Onze Werkwijze.*?<\/section>\s*/s',
            '',
            (string) $page->content,
            1,
        );

        $page->update(['content' => rtrim($content)."\n[werkwijze]\n"]);
    }

    public function down(): void
    {
        $page = Page::query()->where('path', 'over-ons')->first();

        $page?->update(['content' => rtrim(str_replace('[werkwijze]', '', (string) $page->content))]);
    }
};
