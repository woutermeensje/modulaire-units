<?php

namespace App\Support;

/**
 * Gives a page exactly one h1. The content imported from WordPress often has none, or several (a banner title plus
 * the real heading); Google reads the h1 as the subject of the page, so it has to be one, and the right one.
 */
class HeadingOutline
{
    /**
     * Normalise the headings of page content. Headings without text are removed. With a heading outside the content
     * ($hasPageHeading), every h1 in the content becomes an h2; otherwise the first h1 stays and the others become
     * h2, and content without an h1 gets its first heading promoted when that is an h2.
     */
    public static function normalize(string $html, bool $hasPageHeading): string
    {
        $html = (string) preg_replace_callback(
            '/<(h[1-6])\b[^>]*>(.*?)<\/\1>/is',
            fn (array $match): string => trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}") === '' ? '' : $match[0],
            $html,
        );

        $keepFirst = ! $hasPageHeading && static::hasH1($html);
        $html = (string) preg_replace_callback('/<(\/?)h1\b/i', function (array $match) use (&$keepFirst): string {
            if ($keepFirst) {
                if ($match[1] === '/') {
                    $keepFirst = false;
                }

                return $match[0];
            }

            return '<'.$match[1].'h2';
        }, $html);

        if (! $hasPageHeading && ! static::hasH1($html) && preg_match('/<h([2-6])\b[^>]*>.*?<\/h\1>/is', $html, $first, PREG_OFFSET_CAPTURE) && $first[1][0] === '2') {
            $promoted = (string) preg_replace(['/^<h2\b/i', '/<\/h2>$/i'], ['<h1', '</h1>'], $first[0][0]);
            $html = substr_replace($html, $promoted, $first[0][1], strlen($first[0][0]));
        }

        return $html;
    }

    public static function hasH1(string $html): bool
    {
        return (bool) preg_match('/<h1\b/i', $html);
    }
}
