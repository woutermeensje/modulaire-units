<?php

namespace App\Services\Content;

use App\Models\Page;
use App\Support\SiteUrl;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Collection;

/**
 * Turns the (imported Elementor) content of a /huren/ or /kopen/ page into the parts of the offer landing layout:
 * a hero image, a heading with the body text, and the remaining dynamic blocks such as a form.
 */
class OfferLanding
{
    /**
     * Top-level pages that, together with their subpages, use this layout.
     *
     * @var list<string>
     */
    public const SECTIONS = ['huren', 'kopen'];

    /**
     * Shortcodes that are left out of these pages entirely.
     *
     * @var list<string>
     */
    public const HIDDEN_SHORTCODES = ['units_catalog'];

    public function __construct(private ShortcodeRenderer $shortcodes) {}

    public static function appliesTo(Page $page): bool
    {
        return static::section($page) !== null;
    }

    /**
     * The section ("huren" or "kopen") a page belongs to, or null when it is not an offer page.
     */
    public static function section(Page $page): ?string
    {
        $section = explode('/', (string) $page->path)[0];

        return in_array($section, self::SECTIONS, true) ? $section : null;
    }

    /**
     * Remove the hidden shortcodes from a piece of content.
     */
    public static function withoutHiddenShortcodes(?string $content): string
    {
        return (string) preg_replace('/\[('.implode('|', self::HIDDEN_SHORTCODES).')[^\]]*\]/i', '', (string) $content);
    }

    /**
     * @return array{section: string, heading: string, body: string, blocks: list<string>, image: ?string, counterpart: ?Page, related: Collection<int, Page>}
     */
    public function build(Page $page): array
    {
        [$body, $shortcodes] = $this->extract(self::withoutHiddenShortcodes($page->content));

        $heading = $page->title;

        if (preg_match('/^\s*<(h[12])[^>]*>(.*?)<\/\1>/is', $body, $match)) {
            $heading = trim(html_entity_decode(strip_tags($match[2])));
            $body = substr($body, strlen($match[0]));
        }

        $section = (string) static::section($page);
        $isOverview = $page->path === $section;
        $otherSection = $section === 'huren' ? 'kopen' : 'huren';

        return [
            'section' => $section,
            'heading' => $heading,
            'body' => $this->shortcodes->render(trim($body), $page),
            'blocks' => array_values(array_filter(array_map(
                fn (string $shortcode): string => trim($this->shortcodes->render($shortcode, $page)),
                $shortcodes,
            ))),
            'image' => $this->heroImage($page),
            'counterpart' => Page::query()->published()
                ->where('path', $isOverview ? $otherSection : $otherSection.'/'.$page->slug)
                ->first(),
            'related' => $isOverview
                ? $page->children()->published()->get()
                : ($page->parent?->children()->published()->whereKeyNot($page->id)->get() ?? collect()),
        ];
    }

    /**
     * Split the content into body HTML and standalone shortcodes. Elementor markup is flattened to its widgets;
     * sections with a background image (the old banners) and buttons are dropped, the new layout has its own.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function extract(string $content): array
    {
        if (! str_contains($content, 'elementor-widget')) {
            $pattern = '/\[('.implode('|', ShortcodeRenderer::SHORTCODES).')[^\]]*\]/i';
            preg_match_all($pattern, $content, $matches);

            return [(string) preg_replace($pattern, '', $content), $matches[0]];
        }

        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$content.'</body></html>', LIBXML_NOERROR);
        $body = '';
        $shortcodes = [];

        foreach ($document->querySelectorAll('.elementor-widget') as $widget) {
            if ($widget->closest('section[style*="background-image"]')) {
                continue;
            }

            match (true) {
                $widget->classList->contains('elementor-widget-shortcode') => $shortcodes[] = trim($widget->textContent),
                $widget->classList->contains('elementor-widget-heading') => $body .= $this->heading($widget),
                $widget->classList->contains('elementor-widget-text-editor'),
                $widget->classList->contains('elementor-widget-image') => $body .= $widget->innerHTML,
                default => null,
            };
        }

        return [$body, array_values(array_filter($shortcodes))];
    }

    private function heading(Element $widget): string
    {
        $title = $widget->querySelector('.elementor-heading-title');

        if (! $title) {
            return '';
        }

        $tag = in_array(strtolower($title->tagName), ['h2', 'h3', 'h4'], true) ? strtolower($title->tagName) : 'h2';

        return '<'.$tag.'>'.e(trim($title->textContent)).'</'.$tag.'>';
    }

    private function heroImage(Page $page): ?string
    {
        $image = $page->hero['image'] ?? $page->featured_image;

        if (! $image && preg_match("/background-image:\s*url\('?([^')]+)'?\)/", (string) $page->content, $match)) {
            $image = $match[1];
        }

        return SiteUrl::media($image);
    }
}
