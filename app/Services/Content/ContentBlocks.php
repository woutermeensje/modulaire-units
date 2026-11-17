<?php

namespace App\Services\Content;

use App\Models\Page;
use Dom\Element;
use Dom\HTMLDocument;

/**
 * Splits the text of a page (the home page) into an intro and separate blocks: every h2 starts a new block.
 * The text stays one editable field in the CMS; only the presentation changes. A paragraph in a block that holds
 * nothing but one link becomes that block's button.
 */
class ContentBlocks
{
    public function __construct(private ShortcodeRenderer $shortcodes) {}

    /**
     * Null when the content has fewer than two h2 headings: then the page shows its content as before.
     *
     * @return array{title: ?string, intro: string, blocks: list<array{heading: string, body: string, button: ?array{label: string, url: string}}>}|null
     */
    public function build(Page $page): ?array
    {
        $content = (string) $page->content;

        if (substr_count(strtolower($content), '<h2') < 2) {
            return null;
        }

        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$content.'</body></html>', LIBXML_NOERROR);
        $widgets = $document->querySelectorAll('.elementor-widget-text-editor');
        $containers = $widgets->length > 0 ? iterator_to_array($widgets) : [$document->body];

        $title = null;
        $intro = '';
        $blocks = [];

        foreach ($containers as $container) {
            foreach (iterator_to_array($container->childNodes) as $node) {
                $isElement = $node instanceof Element;
                $tag = $isElement ? strtolower($node->tagName) : '';

                if ($tag === 'h1' && $title === null && $blocks === []) {
                    $title = $this->text($node);

                    continue;
                }

                if ($tag === 'h2') {
                    $blocks[] = ['heading' => $this->text($node), 'body' => '', 'button' => null];

                    continue;
                }

                if ($blocks !== [] && $tag === 'p' && $button = $this->button($node)) {
                    $blocks[array_key_last($blocks)]['button'] = $button;

                    continue;
                }

                $html = $document->saveHtml($node);

                if ($blocks === []) {
                    $intro .= $html;
                } else {
                    $blocks[array_key_last($blocks)]['body'] .= $html;
                }
            }
        }

        if (count($blocks) < 2) {
            return null;
        }

        return [
            'title' => $title,
            'intro' => $this->render($intro, $page),
            'blocks' => array_map(fn (array $block): array => ['body' => $this->render($block['body'], $page)] + $block, $blocks),
        ];
    }

    /**
     * The link of a paragraph that contains only that link (no other text), as a button.
     *
     * @return array{label: string, url: string}|null
     */
    private function button(Element $paragraph): ?array
    {
        $links = $paragraph->querySelectorAll('a[href]');

        if ($links->length !== 1) {
            return null;
        }

        $link = $links->item(0);
        $label = $this->text($link);

        if ($label === '' || $label !== $this->text($paragraph)) {
            return null;
        }

        return ['label' => $label, 'url' => (string) $link->getAttribute('href')];
    }

    private function text(Element $element): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', (string) $element->textContent)));
    }

    private function render(string $html, Page $page): string
    {
        return trim($this->shortcodes->render(trim(str_replace('&nbsp;', ' ', $html)), $page));
    }
}
