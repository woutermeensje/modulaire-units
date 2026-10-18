<?php

namespace App\Services\WordPress;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;

/**
 * Cleans HTML from the WordPress site so it can be stored as page content:
 * - dynamic blocks (catalog, tiles, team, vacancies, forms) become shortcodes
 * - Cloudflare-obfuscated e-mail addresses are decoded
 * - Elementor background slideshows become plain background images
 * - fonts become Work Sans, URLs become local
 */
class HtmlTransformer
{
    /** Attributes that only Elementor's JavaScript or WordPress internals used. */
    private const STRIPPED_ATTRIBUTES = ['data-id', 'data-element_type', 'data-e-type', 'data-widget_type', 'data-settings', 'srcset', 'sizes', 'data-elementor-post-type'];

    public function __construct(private UrlRewriter $urls) {}

    public static function document(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="__wp_root">'.$html.'</div></body></html>', LIBXML_NOERROR);
    }

    public static function inner(Node $node): string
    {
        return $node instanceof Element ? $node->innerHTML : (string) $node->textContent;
    }

    public static function decodeCloudflareEmail(string $hex): string
    {
        $key = hexdec(substr($hex, 0, 2));
        $email = '';
        for ($i = 2; $i < strlen($hex); $i += 2) {
            $email .= chr(hexdec(substr($hex, $i, 2)) ^ $key);
        }

        return $email;
    }

    public function transform(string $html): string
    {
        $document = self::document($html);
        $root = $document->getElementById('__wp_root');

        $this->replaceDynamicBlocks($document, $root);
        $this->decodeEmails($document, $root);
        $this->flattenSlideshows($root);
        $this->cleanElements($root);

        $output = $root->innerHTML;
        $output = preg_replace('/font-family\s*:\s*[^;"]*(Poppins|Inter|Balgin|Roboto)[^;"]*/i', "font-family: 'Work Sans', sans-serif", $output);
        $output = preg_replace("/\n\s*\n+/", "\n", $output);

        return trim($this->urls->rewriteHtml($output));
    }

    private function replaceDynamicBlocks(HTMLDocument $document, Element $root): void
    {
        foreach ($root->querySelectorAll('.mh-units-catalog-layout') as $layout) {
            $container = $layout->closest('.elementor-shortcode') ?? $layout->parentElement;
            $title = trim((string) $container->querySelector('.mh-units-catalog__title')?->textContent);
            $types = [];
            foreach ($container->querySelectorAll('.mh-units-catalog__select-hidden input[name="mh_type[]"]') as $input) {
                $types[] = $input->getAttribute('value');
            }
            $attributes = ['title' => $title];
            if ($types) {
                $attributes['type'] = implode(',', array_unique($types));
            }
            $this->replace($document, $container, 'units_catalog', $attributes);
        }

        foreach ($root->querySelectorAll('.muo-grid') as $grid) {
            $firstLink = $grid->querySelector('a.muo-card')?->getAttribute('href') ?? '';
            $parent = explode('/', trim((string) parse_url($firstLink, PHP_URL_PATH), '/'))[0] ?? '';
            preg_match('/muo-cols-(\d+)/', (string) $grid->getAttribute('class'), $columns);
            $container = $grid->closest('.elementor-shortcode') ?? $grid;
            if ($container !== $grid && $container->querySelectorAll('.muo-grid')->length > 1) {
                $container = $grid;
            }
            $this->replace($document, $container, 'subpage_grid', ['parent' => $parent, 'columns' => $columns[1] ?? '3']);
        }

        foreach ($root->querySelectorAll('.mh-team-overview') as $team) {
            preg_match('/--cols-(\d+)/', (string) $team->getAttribute('class'), $columns);
            $this->replace($document, $team->closest('.elementor-shortcode') ?? $team, 'team', ['columns' => $columns[1] ?? '3']);
        }

        foreach ($root->querySelectorAll('div.job_listings') as $listings) {
            $this->replace($document, $listings, 'vacatures', []);
        }

        foreach ($root->querySelectorAll('.gform_wrapper, .elementor-widget-form, .elementor-widget-wp-widget-gform') as $form) {
            if (! $form->isConnected) {
                continue;
            }
            $container = $form->closest('.elementor-widget-wp-widget-gform') ?? $form;
            $isCompact = $container->classList->contains('elementor-widget-form');
            $this->replace($document, $container, 'informatie_formulier', $isCompact ? ['title' => 'none'] : []);
        }
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function replace(HTMLDocument $document, Element $element, string $shortcode, array $attributes): void
    {
        if (! $element->isConnected) {
            return;
        }

        $attributeString = collect($attributes)
            ->filter(fn (?string $value): bool => $value !== null && $value !== '')
            ->map(fn (string $value, string $key): string => $key.'="'.str_replace('"', '&quot;', $value).'"')
            ->implode(' ');

        $element->replaceWith($document->createTextNode("\n[{$shortcode}".($attributeString ? ' '.$attributeString : '')."]\n"));
    }

    private function decodeEmails(HTMLDocument $document, Element $root): void
    {
        foreach ($root->querySelectorAll('[data-cfemail]') as $element) {
            $element->replaceWith($document->createTextNode(self::decodeCloudflareEmail((string) $element->getAttribute('data-cfemail'))));
        }

        foreach ($root->querySelectorAll('a[href*="email-protection"]') as $link) {
            $href = (string) $link->getAttribute('href');
            $hex = str_contains($href, '#') ? substr($href, strpos($href, '#') + 1) : '';
            $email = $hex !== '' ? self::decodeCloudflareEmail($hex) : trim((string) $link->textContent);
            $link->setAttribute('href', 'mailto:'.$email);
            $link->classList->remove('__cf_email__');
        }
    }

    private function flattenSlideshows(Element $root): void
    {
        foreach ($root->querySelectorAll('[data-settings*="background_slideshow_gallery"]') as $element) {
            $settings = json_decode(html_entity_decode((string) $element->getAttribute('data-settings')), true);
            $url = $settings['background_slideshow_gallery'][0]['url'] ?? null;

            if ($url) {
                $style = trim((string) $element->getAttribute('style'));
                $element->setAttribute('style', ltrim($style.';background-image:url(\''.$url.'\');background-size:cover;background-position:center;', ';'));
            }
        }
    }

    private function cleanElements(Element $root): void
    {
        foreach ($root->querySelectorAll('script, noscript, style, link, meta') as $element) {
            $element->remove();
        }

        foreach ($root->querySelectorAll('*') as $element) {
            foreach (self::STRIPPED_ATTRIBUTES as $attribute) {
                $element->removeAttribute($attribute);
            }
            $element->classList->remove('elementor-invisible');
        }
    }
}
