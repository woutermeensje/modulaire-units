<?php

namespace App\Services\Content;

use App\Models\Page;
use App\Support\SiteUrl;
use Dom\Element;
use Dom\HTMLDocument;

/**
 * Turns the (imported Elementor) content of a page with the "service" template into a hero and alternating
 * text/image blocks. Every Elementor inner section becomes one block; a first block without an image is the intro.
 */
class ServiceLanding
{
    public function __construct(private ShortcodeRenderer $shortcodes) {}

    /**
     * @return array{title: string, intro: string, image: ?string, imageAlt: string, blocks: list<array{heading: ?string, body: string, image: ?string, alt: string}>}
     */
    public function build(Page $page): array
    {
        $blocks = $this->blocks((string) $page->content);
        $intro = '';
        $title = $page->title;

        if ($blocks !== [] && $blocks[0]['image'] === null) {
            $first = array_shift($blocks);
            $title = $first['heading'] ?? $title;
            $intro = $first['body'];
        }

        $image = SiteUrl::media($page->hero['image'] ?? $page->featured_image) ?? ($blocks[0]['image'] ?? null);

        return [
            'title' => $title,
            'intro' => $this->shortcodes->render($intro, $page),
            'image' => $image,
            'imageAlt' => $blocks[0]['alt'] ?? '',
            'blocks' => array_map(fn (array $block): array => ['body' => $this->shortcodes->render($block['body'], $page)] + $block, $blocks),
        ];
    }

    /**
     * @return list<array{heading: ?string, body: string, image: ?string, alt: string}>
     */
    private function blocks(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$content.'</body></html>', LIBXML_NOERROR);
        $sections = $document->querySelectorAll('section.elementor-inner-section');
        $containers = $sections->length > 0 ? iterator_to_array($sections) : [$document->body];

        return array_values(array_filter(array_map(fn (Element $section): array => $this->block($section), $containers),
            fn (array $block): bool => $block['heading'] !== null || trim(strip_tags($block['body'])) !== '' || $block['image'] !== null));
    }

    /**
     * @return array{heading: ?string, body: string, image: ?string, alt: string}
     */
    private function block(Element $section): array
    {
        $body = '';

        foreach ($section->querySelectorAll('.elementor-widget-text-editor') as $widget) {
            $body .= $widget->innerHTML;
        }

        if ($body === '' && ! $section->querySelector('.elementor-widget')) {
            $body = $section->innerHTML;
        }

        $heading = null;

        if (preg_match('/^\s*<(h[1-3])[^>]*>(.*?)<\/\1>/is', $body, $match)) {
            $heading = trim(html_entity_decode(strip_tags($match[2])));
            $body = substr($body, strlen($match[0]));
        }

        $image = $section->querySelector('img');

        return [
            'heading' => $heading,
            'body' => trim(str_replace('&nbsp;', ' ', $body)),
            'image' => $image ? SiteUrl::media($image->getAttribute('src')) : null,
            'alt' => (string) $image?->getAttribute('alt'),
        ];
    }
}
