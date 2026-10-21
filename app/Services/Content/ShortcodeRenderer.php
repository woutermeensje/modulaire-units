<?php

namespace App\Services\Content;

use App\Models\Page;
use App\Models\TeamMember;
use App\Models\Unit;
use App\Models\UnitTerm;
use App\Models\Vacancy;
use Illuminate\Support\Str;

/**
 * Renders the dynamic blocks inside page content, written as WordPress style shortcodes:
 *
 *   [units_catalog ...]                                 removed overview; old tags in content render nothing
 *   [units_grid type="kantoorunit" limit="3"]           plain grid of unit cards
 *   [subpage_grid parent="kopen" columns="3"]           image tiles of the child pages of a page
 *   [team columns="3"]                                  team members
 *   [vacatures]                                         open vacancies
 *   [informatie_formulier]                              information request form
 *   [werkwijze]                                         the steps of how we work, from first contact to pick-up
 */
class ShortcodeRenderer
{
    /** @var list<string> */
    public const SHORTCODES = ['units_catalog', 'units_grid', 'subpage_grid', 'team', 'vacatures', 'informatie_formulier', 'werkwijze'];

    private ?Page $page = null;

    public function render(?string $content, ?Page $page = null): string
    {
        $this->page = $page;

        if ($content === null || $content === '') {
            return '';
        }

        $pattern = '/\[('.implode('|', self::SHORTCODES).')((?:\s+[a-z_]+="[^"]*")*)\s*\/?\]/i';

        return preg_replace_callback($pattern, function (array $match): string {
            $attributes = $this->parseAttributes($match[2]);

            return match (strtolower($match[1])) {
                'units_catalog' => '',
                'units_grid' => $this->unitsGrid($attributes),
                'subpage_grid' => $this->subpageGrid($attributes),
                'team' => $this->team($attributes),
                'vacatures' => $this->vacancies(),
                'informatie_formulier' => view('shortcodes.information-form', ['attributes' => $attributes])->render(),
                'werkwijze' => view('shortcodes.work-steps')->render(),
            };
        }, $content);
    }

    /**
     * @return array<string, string>
     */
    public function parseAttributes(string $raw): array
    {
        preg_match_all('/([a-z_]+)="([^"]*)"/i', $raw, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $m): array => [strtolower($m[1]) => html_entity_decode($m[2])])->all();
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function unitsGrid(array $attributes): string
    {
        $units = Unit::query()->published()->with('terms')
            ->when($attributes['type'] ?? null, fn ($query, string $type) => $query->whereHas('terms', fn ($terms) => $terms
                ->where('taxonomy', UnitTerm::TYPE)->whereIn('slug', explode(',', $type))))
            ->orderBy('sort')->latest('published_at')
            ->limit((int) ($attributes['limit'] ?? 12))
            ->get();

        return view('shortcodes.units-grid', ['units' => $units])->render();
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function subpageGrid(array $attributes): string
    {
        $parent = Page::query()->where('path', trim($attributes['parent'] ?? '', '/'))->first();

        $pages = $parent?->children()->published()->get() ?? collect();

        if (! empty($attributes['exclude'])) {
            $excluded = array_map('trim', explode(',', $attributes['exclude']));
            $pages = $pages->reject(fn (Page $page): bool => in_array($page->slug, $excluded, true));
        }

        return view('shortcodes.subpage-grid', [
            'pages' => $pages,
            'columns' => (int) ($attributes['columns'] ?? 3),
        ])->render();
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function team(array $attributes): string
    {
        return view('shortcodes.team', [
            'members' => TeamMember::query()->where('is_visible', true)->orderBy('sort')->orderBy('name')->get(),
            'columns' => (int) ($attributes['columns'] ?? 3),
        ])->render();
    }

    private function vacancies(): string
    {
        return view('shortcodes.vacancies', [
            'vacancies' => Vacancy::query()->published()->where('is_filled', false)->latest('published_at')->get(),
        ])->render();
    }

    public static function strip(?string $content): string
    {
        return trim((string) preg_replace('/\[('.implode('|', self::SHORTCODES).')[^\]]*\]/i', '', Str::of((string) $content)->toString()));
    }
}
