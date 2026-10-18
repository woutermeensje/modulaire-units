<?php

namespace Tests\Unit;

use App\Services\WordPress\HtmlTransformer;
use App\Services\WordPress\UrlRewriter;
use PHPUnit\Framework\TestCase;

class WordPressHtmlTransformerTest extends TestCase
{
    private function transformer(): HtmlTransformer
    {
        return new HtmlTransformer(new UrlRewriter('https://modulairehuisvesting.nl'));
    }

    public function test_it_rewrites_links_and_uploads_to_local_urls(): void
    {
        $html = $this->transformer()->transform('<a href="https://modulairehuisvesting.nl/kopen/">Kopen</a><img src="https://modulairehuisvesting.nl/wp-content/uploads/2026/05/foto-1024x768.png" srcset="x 1w">');

        $this->assertSame('<a href="/kopen/">Kopen</a><img src="/storage/media/2026/05/foto-1024x768.png">', $html);
    }

    public function test_it_decodes_cloudflare_protected_email_addresses(): void
    {
        $this->assertSame('informatie@modulairehuisvesting.nl', HtmlTransformer::decodeCloudflareEmail('8fe6e1e9e0fde2eefbe6eacfe2e0ebfae3eee6fdeae7fae6fcf9eafcfbe6e1e8a1e1e3'));
    }

    public function test_it_turns_the_units_catalog_into_a_shortcode(): void
    {
        $html = '<div class="elementor-shortcode"><div class="mh-units-catalog__intro"><h1 class="mh-units-catalog__title">Bouwunits</h1></div>'
            .'<div class="mh-catalog-layout mh-units-catalog-layout"><div class="mh-units-catalog__select-hidden"><input name="mh_type[]" value="bouw-unit"></div></div></div>';

        $this->assertSame('[units_catalog title="Bouwunits" type="bouw-unit"]', $this->transformer()->transform($html));
    }

    public function test_it_replaces_fonts_with_work_sans(): void
    {
        $this->assertStringContainsString("font-family: 'Work Sans', sans-serif", $this->transformer()->transform('<h2 style="font-family: Poppins, sans-serif;">Titel</h2>'));
    }

    public function test_upload_paths_drop_invisible_characters(): void
    {
        $rewriter = new UrlRewriter('https://modulairehuisvesting.nl');

        $this->assertSame('media/2026/02/Schermafbeelding.png', $rewriter->uploadPath("https://modulairehuisvesting.nl/wp-content/uploads/2026/02/Scherm\u{AD}afbeelding.png"));
        $this->assertNull($rewriter->uploadPath('https://example.com/wp-content/uploads/foto.png'));
    }
}
