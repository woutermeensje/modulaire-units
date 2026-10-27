<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTrailingSlash;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Unit;
use App\Models\UnitTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_the_home_page(): void
    {
        Page::factory()->create(['title' => 'Home', 'slug' => '', 'content' => '<p>Welkom bij de modulaire units</p>']);

        $this->get('/')->assertOk()->assertSee('Welkom bij de modulaire units', false)->assertSee('Inloggen');
    }

    public function test_nested_pages_resolve_by_their_full_path_with_trailing_slash(): void
    {
        $parent = Page::factory()->create(['slug' => 'kopen']);
        Page::factory()->create(['slug' => 'bouwunits', 'parent_id' => $parent->id, 'content' => '<p>Bouwunits kopen tekst</p>']);

        $this->get('/kopen/bouwunits/')->assertOk()->assertSee('Bouwunits kopen tekst', false);
    }

    public function test_urls_without_trailing_slash_redirect_permanently(): void
    {
        $middleware = new EnsureTrailingSlash;
        $next = fn () => response('ok');

        $redirect = $middleware->handle(Request::create('https://modulairehuisvesting.nl/contact?x=1'), $next);
        $this->assertSame(301, $redirect->getStatusCode());
        $this->assertSame('https://modulairehuisvesting.nl/contact/?x=1', $redirect->headers->get('Location'));

        $this->assertSame('ok', $middleware->handle(Request::create('https://modulairehuisvesting.nl/contact/'), $next)->getContent());
        $this->assertSame('ok', $middleware->handle(Request::create('https://modulairehuisvesting.nl/sitemap.xml'), $next)->getContent());
    }

    public function test_draft_pages_are_hidden_from_visitors(): void
    {
        Page::factory()->create(['slug' => 'concept', 'status' => 'draft']);

        $this->get('/concept/')->assertNotFound();
    }

    public function test_blog_posts_live_in_the_site_root(): void
    {
        Post::factory()->create(['title' => 'Wat is modulaire huisvesting?', 'slug' => 'wat-is-modulaire-huisvesting']);

        $this->get('/wat-is-modulaire-huisvesting/')->assertOk()->assertSee('Wat is modulaire huisvesting?');
        $this->get('/blog/')->assertOk()->assertSee('Wat is modulaire huisvesting?');
    }

    public function test_units_catalog_shortcode_renders_nothing(): void
    {
        Unit::factory()->create(['title' => 'Kantoorunit 20ft']);
        Page::factory()->create(['slug' => 'gebruikt', 'content' => '[units_catalog title="Gebruikt"]<p>Gebruikte units tekst</p>']);

        $this->get('/gebruikt/')
            ->assertOk()
            ->assertSee('Gebruikte units tekst')
            ->assertDontSee('units_catalog')
            ->assertDontSee('Kantoorunit 20ft')
            ->assertDontSee('mh-units-catalog', false);
    }

    public function test_rent_pages_use_the_offer_layout_without_units_catalog(): void
    {
        Unit::factory()->create(['title' => 'Bouwunit 20ft']);
        $rent = Page::factory()->create(['slug' => 'huren']);
        $buy = Page::factory()->create(['slug' => 'kopen']);
        Page::factory()->create(['slug' => 'bouwunits', 'parent_id' => $buy->id, 'title' => 'Bouwunits kopen']);
        Page::factory()->create(['slug' => 'kantines', 'parent_id' => $rent->id, 'title' => 'Kantines huren']);
        Page::factory()->create([
            'slug' => 'bouwunits',
            'parent_id' => $rent->id,
            'title' => 'Bouwunits huren',
            'content' => '<div class="elementor">'
                .'<section style="background-image:url(\'/storage/media/banner.png\')"><div class="elementor-widget elementor-widget-heading"><h2 class="elementor-heading-title">Oude banner</h2></div></section>'
                .'<section><div class="elementor-widget elementor-widget-shortcode">[units_catalog title="Bouwunits huren" type="bouw-unit"]</div></section>'
                .'<section><div class="elementor-widget elementor-widget-text-editor"><h2>Tijdelijk bouwunits huren</h2><p>Snel leverbaar op de bouwplaats.</p></div></section>'
                .'</div>',
        ]);

        $this->get('/huren/bouwunits/')
            ->assertOk()
            ->assertSee('mh-offer-hero', false)
            ->assertSee('/storage/media/banner.png', false)
            ->assertSee('Tijdelijk bouwunits huren')
            ->assertSee('Snel leverbaar op de bouwplaats.')
            ->assertDontSee('Oude banner')
            ->assertDontSee('Bouwunit 20ft')
            ->assertSee('href="/kopen/bouwunits/"', false)
            ->assertSee('Kantines huren');
    }

    public function test_buy_pages_and_overviews_use_the_offer_layout(): void
    {
        Unit::factory()->create(['title' => 'Bouwunit 20ft']);
        $rent = Page::factory()->create(['slug' => 'huren', 'title' => 'Huren']);
        $buy = Page::factory()->create(['slug' => 'kopen', 'title' => 'Kopen', 'content' => '[units_catalog title="Kopen"]<h1>Modulaire units kopen</h1>']);
        Page::factory()->create(['slug' => 'bouwunits', 'parent_id' => $rent->id, 'title' => 'Bouwunits huren']);
        Page::factory()->create(['slug' => 'bouwunits', 'parent_id' => $buy->id, 'title' => 'Bouwunits kopen', 'content' => '[units_catalog title="Bouwunits"]<p>Bouwunits kopen tekst</p>']);

        $this->get('/kopen/bouwunits/')
            ->assertOk()
            ->assertSee('mh-offer-hero', false)
            ->assertSee('Bouwunits kopen tekst')
            ->assertSee('href="/huren/bouwunits/"', false)
            ->assertSee('Liever flexibel en zonder investering?')
            ->assertDontSee('Bouwunit 20ft')
            ->assertDontSee('mh-units-catalog', false);

        $this->get('/kopen/')
            ->assertOk()
            ->assertSee('<h2 class="mh-offer-intro__heading">Modulaire units kopen</h2>', false)
            ->assertSee('Alle mogelijkheden:')
            ->assertSee('href="/kopen/bouwunits/" class="muo-card', false)
            ->assertSee("background-image: url('/marketing/units/bouwunits.jpg')", false)
            ->assertSee('href="/huren/"', false)
            ->assertDontSee('mh-units-catalog', false);
    }

    public function test_work_steps_shortcode_renders_numbered_steps(): void
    {
        Page::factory()->create(['slug' => 'over-ons', 'content' => '<p>Over ons tekst</p>[werkwijze]']);

        $this->get('/over-ons/')
            ->assertOk()
            ->assertSee('Over ons tekst')
            ->assertSee('Onze werkwijze')
            ->assertSee('Wij halen de unit(s) op')
            ->assertSee('mailto:informatie@modulairehuisvesting.nl', false);
    }

    public function test_service_template_renders_hero_and_alternating_blocks(): void
    {
        Page::factory()->create([
            'slug' => 'transport-plaatsing',
            'title' => 'Transport & Plaatsing',
            'template' => 'service',
            'content' => '<div class="elementor">'
                .'<section class="elementor-section elementor-inner-section"><div class="elementor-widget elementor-widget-text-editor"><h1>Transport van units</h1><p>Wij regelen het transport.</p></div></section>'
                .'<section class="elementor-section elementor-inner-section"><div class="elementor-widget elementor-widget-image"><img src="/storage/media/kraan.png" alt="Kraan"></div><div class="elementor-widget elementor-widget-text-editor"><h1>De Kosten</h1><p>Tarieven op aanvraag.</p></div></section>'
                .'</div>',
        ]);

        $this->get('/transport-plaatsing/')
            ->assertOk()
            ->assertSee('<h1 id="mh-service-title">Transport van units</h1>', false)
            ->assertSee('Wij regelen het transport.')
            ->assertSee('<h2 class="mh-service-block__title">De Kosten</h2>', false)
            ->assertSee('/storage/media/kraan.png', false)
            ->assertSee('Vragen over transport &amp; plaatsing?', false);
    }

    public function test_modulaire_units_page_lists_every_unit_type_with_rent_and_buy_links(): void
    {
        $rent = Page::factory()->create(['slug' => 'huren', 'title' => 'Huren']);
        $buy = Page::factory()->create(['slug' => 'kopen', 'title' => 'Kopen']);
        Page::factory()->create(['slug' => 'bouwunits', 'title' => 'Bouwunits huren', 'parent_id' => $rent->id]);
        Page::factory()->create(['slug' => 'bouwunits', 'title' => 'Bouwunits', 'parent_id' => $buy->id]);
        Page::factory()->create(['slug' => 'kantines', 'title' => 'Kantines huren', 'parent_id' => $rent->id]);
        Page::factory()->create(['slug' => 'kantine', 'title' => 'Kantine units kopen', 'parent_id' => $buy->id]);
        Page::factory()->create(['slug' => 'slaapunits', 'title' => 'Slaapunits kopen', 'parent_id' => $buy->id]);
        Page::factory()->create(['slug' => 'modulaire-units', 'title' => 'Modulaire units', 'excerpt' => 'Het complete aanbod.', 'content' => '']);

        $this->get('/modulaire-units/')
            ->assertOk()
            ->assertSee('<h1 id="mh-unit-types-title">Modulaire units</h1>', false)
            ->assertSee('mh-offer-hero__panel', false)
            ->assertSee('Het complete aanbod.')
            ->assertSeeInOrder(['Bouwunits', 'Kantines', 'Slaapunits'])
            ->assertSee('href="/huren/bouwunits/"', false)
            ->assertSee('href="/kopen/bouwunits/"', false)
            ->assertSee('href="/kopen/kantine/"', false)
            ->assertSee('aria-label="Slaapunits kopen"', false)
            ->assertDontSee('aria-label="Slaapunits huren"', false)
            ->assertSee("background-image: url('/marketing/units/bouwunits.jpg')", false);
    }

    public function test_unit_detail_and_taxonomy_archive(): void
    {
        $term = UnitTerm::factory()->create(['taxonomy' => UnitTerm::CONDITION, 'name' => 'Nieuw', 'slug' => 'nieuw']);
        $unit = Unit::factory()->create(['title' => '20ft Modulaire Unit', 'slug' => '20ft-modulaire-unit', 'price_buy' => 'Prijs op aanvraag.']);
        $unit->terms()->attach($term);

        $this->get('/units/20ft-modulaire-unit/')->assertOk()->assertSee('20ft Modulaire Unit')->assertSee('Prijs op aanvraag.');
        $this->get('/conditie/nieuw/')->assertOk()->assertSee('20ft Modulaire Unit');
    }

    public function test_configured_redirects_are_followed(): void
    {
        Redirect::factory()->create(['from_path' => '/winkelwagen', 'to_path' => '/mijn-offerte/']);

        $this->get('/winkelwagen/')->assertRedirect('/mijn-offerte/');
    }

    public function test_old_wordpress_upload_urls_redirect_to_storage(): void
    {
        $this->get('/wp-content/uploads/2026/05/foto.png')->assertRedirect('/storage/media/2026/05/foto.png');
    }

    public function test_sitemap_lists_published_content(): void
    {
        Page::factory()->create(['slug' => 'over-ons']);
        Page::factory()->create(['slug' => 'geheim', 'status' => 'draft']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/over-ons/')
            ->assertDontSee('/geheim/');
    }

    public function test_unknown_urls_return_404(): void
    {
        $this->get('/bestaat-niet/')->assertNotFound()->assertSee('Pagina niet gevonden');
    }
}
