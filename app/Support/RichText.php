<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Cleans HTML from the rich text editor (Quill) so it can go into a PDF: only the formats the editor's toolbar has
 * are kept (headings, bold, italic, underline, lists, links), everything else is unwrapped to its text.
 *
 * Quill 2 writes every list as <ol>, with data-list="bullet" or "ordered" on each item; those are turned into real
 * <ul> and <ol> lists, as dompdf would otherwise number the bullet points.
 */
class RichText
{
    /** Tags that are kept, with the attributes they may keep. */
    private const ALLOWED = [
        'p' => ['class'],
        'br' => [],
        'h2' => [],
        'h3' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'ol' => [],
        'ul' => [],
        'li' => ['class', 'data-list'],
        'a' => ['href'],
    ];

    /** Tags that are removed together with what is in them. */
    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'head', 'title'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="rich-text">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('rich-text') ?? $document->documentElement;
        if (! $root instanceof DOMElement) {
            return '';
        }

        self::cleanChildren($root);
        self::splitQuillLists($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Whether there is any text in the HTML, so an editor with only an empty paragraph counts as empty.
     */
    public static function isEmpty(?string $html): bool
    {
        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}") === '';
    }

    private static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $node->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED, true)) {
                $node->removeChild($child);

                continue;
            }

            self::cleanChildren($child);

            if (! array_key_exists($tag, self::ALLOWED)) {
                // Keep the text, lose the tag.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                if (! in_array($attribute->name, self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attribute->name);
                }
            }

            if ($child->hasAttribute('class')) {
                // Only Quill's indents (ql-indent-1 and so on) mean something in the PDF.
                $classes = array_filter(preg_split('/\s+/', $child->getAttribute('class')) ?: [], fn (string $class): bool => (bool) preg_match('/^ql-indent-\d$/', $class));
                $classes ? $child->setAttribute('class', implode(' ', $classes)) : $child->removeAttribute('class');
            }

            if ($tag === 'a' && ! preg_match('#^(https?://|mailto:|tel:)#i', trim($child->getAttribute('href')))) {
                $child->removeAttribute('href');
            }
        }
    }

    /**
     * Turn each Quill <ol> into runs of <ul> (bullets) and <ol> (numbers), in the order of its items.
     */
    private static function splitQuillLists(DOMElement $root): void
    {
        foreach (iterator_to_array($root->getElementsByTagName('ol')) as $list) {
            $items = array_filter(iterator_to_array($list->childNodes), fn (DOMNode $item): bool => $item instanceof DOMElement && $item->hasAttribute('data-list'));
            if ($items === []) {
                continue;
            }

            $document = $list->ownerDocument;
            $current = null;
            $currentType = null;

            foreach (iterator_to_array($list->childNodes) as $item) {
                if (! $item instanceof DOMElement) {
                    continue;
                }

                $type = $item->getAttribute('data-list') === 'ordered' ? 'ol' : 'ul';
                if ($current === null || $type !== $currentType) {
                    $current = $document->createElement($type);
                    $currentType = $type;
                    $list->parentNode->insertBefore($current, $list);
                }

                $item->removeAttribute('data-list');
                $current->appendChild($item);
            }

            $list->parentNode->removeChild($list);
        }

        foreach (iterator_to_array($root->getElementsByTagName('li')) as $item) {
            $item->removeAttribute('data-list');
        }
    }
}
