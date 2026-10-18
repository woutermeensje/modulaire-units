<?php

namespace App\Services\WordPress;

class CssCleaner
{
    /**
     * Fonts used on the WordPress site that are replaced by Work Sans.
     */
    private const REPLACED_FONTS = 'Poppins|Inter|Balgin|Roboto|Sans-serif';

    public function __construct(private UrlRewriter $urls) {}

    public function clean(string $css): string
    {
        $css = $this->removeCustomFontFaces($css);
        $css = $this->replaceFontFamilies($css);
        $css = preg_replace('/@import\s+url\([\'"]?https:\/\/fonts\.googleapis\.com[^)]*\)\s*;?/i', '', $css);
        $css = preg_replace('/\/\*# sourceURL=[^*]*\*\//', '', $css);
        $css = preg_replace('/@charset\s+"[^"]*"\s*;/i', '', $css);
        $css = preg_replace('/(^|[};])\s*\{\s*\}/', '$1', $css);

        return trim($this->urls->rewriteCss($css));
    }

    /**
     * Drop @font-face rules for the old brand fonts; Work Sans is loaded by the layout.
     */
    private function removeCustomFontFaces(string $css): string
    {
        return preg_replace_callback('/@font-face\s*\{[^}]*\}/i', function (array $match): string {
            return preg_match('/font-family:\s*[\'"]?('.self::REPLACED_FONTS.')/i', $match[0]) ? '' : $match[0];
        }, $css);
    }

    private function replaceFontFamilies(string $css): string
    {
        $css = preg_replace_callback('/font-family\s*:\s*([^;}]+)/i', function (array $match): string {
            if (preg_match('/(Poppins|Inter|Balgin|Roboto|Work Sans|var\(\s*--e-global-typography)/i', $match[1])) {
                return "font-family:'Work Sans', sans-serif".(str_contains($match[1], '!important') ? ' !important' : '');
            }

            return $match[0];
        }, $css);

        return preg_replace('/(--e-global-typography-[a-z0-9-]+-font-family\s*:\s*)[^;}]+/i', '$1"Work Sans"', $css);
    }
}
