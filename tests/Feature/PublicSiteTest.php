<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTrailingSlash;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\UnitTerm;
use App\Support\UnitConfiguration;
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
            ->assertDontSee('Bekijk ook:')
            ->assertDontSee('href="/huren/kantines/"', false)
            ->assertSee('Stel je eigen unit samen')
            ->assertSee('<a class="mh-offer-btn mh-offer-btn--dark" href="#unit-samensteller">Units samenstellen</a>', false)
            ->assertSee('id="unit-samensteller"', false)
            ->assertSee('data-composer', false);
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
            ->assertSee('<h1 id="mh-offer-title">Bouwunits kopen</h1>', false)
            ->assertSee('mh-offer-hero', false)
            ->assertSee('Bouwunits kopen tekst')
            ->assertSee('href="/huren/bouwunits/"', false)
            ->assertSee('Liever flexibel en zonder investering?')
            ->assertDontSee('Bouwunit 20ft')
            ->assertDontSee('mh-units-catalog', false);

        $this->get('/kopen/')
            ->assertOk()
            ->assertSee('<h1 id="mh-offer-title">Modulaire Units Kopen</h1>', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('Informatie aanvragen')
            ->assertSee('<h2 class="mh-offer-intro__heading">Modulaire units kopen</h2>', false)
            ->assertDontSee('Alle mogelijkheden:')
            ->assertDontSee('href="/kopen/bouwunits/" class="muo-card', false)
            ->assertSee('href="/huren/"', false)
            ->assertSee('<a class="mh-offer-btn mh-offer-btn--dark" href="#unit-samensteller">Units samenstellen</a>', false)
            ->assertSee('data-composer', false)
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

    public function test_modulaire_units_page_offers_the_unit_composer_instead_of_the_unit_types(): void
    {
        $rent = Page::factory()->create(['slug' => 'huren', 'title' => 'Huren']);
        Page::factory()->create(['slug' => 'bouwunits', 'title' => 'Bouwunits huren', 'parent_id' => $rent->id]);
        Page::factory()->create(['slug' => 'modulaire-units', 'title' => 'Modulaire units', 'excerpt' => 'Het complete aanbod.', 'content' => '']);

        $this->get('/modulaire-units/')
            ->assertOk()
            ->assertSee('<h1 id="mh-unit-types-title">Modulaire units</h1>', false)
            ->assertSee('mh-offer-hero__panel', false)
            ->assertSee('Het complete aanbod.')
            ->assertSee('<a class="mh-offer-btn mh-offer-btn--dark" href="#unit-samensteller">Units samenstellen</a>', false)
            ->assertSee('Stel je eigen unit samen')
            ->assertSee('id="unit-samensteller"', false)
            ->assertSee('data-composer', false)
            ->assertDontSee('Alle typen modulaire units')
            ->assertDontSee('href="/huren/bouwunits/"', false);
    }

    public function test_unit_detail_and_taxonomy_archive(): void
    {
        $term = UnitTerm::factory()->create(['taxonomy' => UnitTerm::CONDITION, 'name' => 'Nieuw', 'slug' => 'nieuw']);
        $unit = Unit::factory()->create(['title' => '20ft Modulaire Unit', 'slug' => '20ft-modulaire-unit', 'price_buy' => 'Prijs op aanvraag.']);
        $unit->terms()->attach($term);

        $this->get('/units/20ft-modulaire-unit/')->assertOk()->assertSee('20ft Modulaire Unit')->assertSee('Prijs op aanvraag.');
        $this->get('/conditie/nieuw/')->assertOk()->assertSee('20ft Modulaire Unit');
    }

    public function test_configured_units_offer_a_3d_view(): void
    {
        Unit::factory()->create(['slug' => 'met-configuratie', 'configuration' => UnitConfiguration::defaults('3x3')]);
        Unit::factory()->create(['slug' => 'zonder-configuratie', 'configuration' => null]);

        $this->get('/units/met-configuratie/')
            ->assertOk()
            ->assertSee('Bekijk in 3D')
            ->assertSee('data-unit-viewer=\'{"configuration":{"size":"3x3"', false)
            ->assertSee('"dimensions":{"length":3,"depth":3,"floors":1}', false);

        $this->get('/units/zonder-configuratie/')->assertOk()->assertDontSee('Bekijk in 3D')->assertDontSee('data-unit-viewer', false);

        Unit::factory()->create(['slug' => 'gekoppeld', 'configuration' => UnitConfiguration::normalize(['size' => '6x3', 'modules' => 3])]);
        $this->get('/units/gekoppeld/')->assertOk()->assertSee('"dimensions":{"length":6,"depth":9,"floors":1}', false);
    }

    public function test_configured_redirects_are_followed(): void
    {
        Redirect::factory()->create(['from_path' => '/winkelwagen', 'to_path' => '/mijn-offerte/']);

        $this->get('/winkelwagen/')->assertRedirect(url('mijn-offerte').'/');
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

    public function test_menu_migration_turns_aanbod_into_units_with_the_unit_composer(): void
    {
        $offer = MenuItem::query()->create(['menu' => 'main', 'label' => 'Aanbod', 'url' => '/modulaire-units/', 'sort' => 0]);
        foreach (['Kopen' => '/kopen/', 'Huren' => '/huren/', 'Gebruikt' => '/gebruikt/'] as $label => $url) {
            MenuItem::query()->create(['menu' => 'main', 'parent_id' => $offer->id, 'label' => $label, 'url' => $url, 'sort' => $offer->children()->count()]);
        }
        $migration = require database_path('migrations/2026_10_30_082253_rename_aanbod_menu_to_units_with_unit_composer.php');

        $migration->up();
        $migration->up();

        $this->assertSame('Units', $offer->refresh()->label);
        $this->assertSame(['Kopen', 'Huren', 'Gebruikt', 'Units samenstellen'], $offer->children()->orderBy('sort')->pluck('label')->all());
        $this->assertSame('/unit-samensteller/', $offer->children()->where('label', 'Units samenstellen')->value('url'));

        $migration->down();

        $this->assertSame('Aanbod', $offer->refresh()->label);
        $this->assertSame(3, $offer->children()->count());
    }

    public function test_content_migration_removes_the_unit_overview_grids_and_redirects_aanbod(): void
    {
        $grid = fn (string $section): string => '<section class="elementor-section elementor-top-section a"><div><h2>Bekijk het overzicht - '.$section.'</h2>[subpage_grid parent="'.$section.'" columns="3"]</div></section>';
        $about = '<section class="elementor-section elementor-top-section b"><section class="elementor-section elementor-inner-section"><h1>Over ons</h1></section></section>';
        $home = Page::factory()->create(['slug' => '', 'content' => '<div class="elementor">'.$grid('kopen').$about.'</div>']);
        $offer = Page::factory()->create(['slug' => 'aanbod', 'content' => '<div class="elementor">'.$grid('kopen').$grid('huren').'</div>']);
        $migration = require database_path('migrations/2026_10_30_095410_remove_unit_overview_grids_from_page_content.php');

        $migration->up();
        $migration->up();

        $this->assertSame('<div class="elementor">'.$about.'</div>', $home->refresh()->content);
        $this->assertSame('<div class="elementor"></div>', $offer->refresh()->content);
        $this->get('/aanbod/')->assertRedirect(url('modulaire-units').'/');

        $migration->down();

        $this->assertDatabaseEmpty('redirects');
    }

    public function test_redirects_keep_the_trailing_slash_so_there_is_no_second_redirect(): void
    {
        Redirect::factory()->create(['from_path' => '/aanbod', 'to_path' => '/modulaire-units/']);

        $this->get('/aanbod/')->assertStatus(301)->assertHeader('Location', url('modulaire-units').'/');
    }

    public function test_pages_without_meta_description_fall_back_to_their_content(): void
    {
        Page::factory()->create([
            'slug' => 'werkwijze',
            'excerpt' => null,
            'meta_description' => null,
            'content' => '[work_steps]<h2>Onze werkwijze</h2><p>'.str_repeat('Wij plaatsen modulaire units door heel Nederland. ', 6).'</p>',
        ]);

        $response = $this->get('/werkwijze/')->assertOk();

        preg_match('/<meta name="description" content="([^"]*)">/', $response->getContent(), $match);
        $this->assertStringStartsWith('Onze werkwijze Wij plaatsen modulaire units', html_entity_decode($match[1]));
        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($match[1])));
        $this->assertStringEndsWith('…', html_entity_decode($match[1]));
    }

    public function test_unit_taxonomy_archives_have_a_title_and_description(): void
    {
        $unit = Unit::factory()->create();
        $term = UnitTerm::factory()->create(['taxonomy' => UnitTerm::CONDITION, 'slug' => 'nieuw', 'name' => 'Nieuw', 'description' => null]);
        $unit->terms()->attach($term);

        $this->get('/conditie/nieuw/')
            ->assertOk()
            ->assertSee('<title>Nieuw - Modulaire units - ', false)
            ->assertSee('<meta name="description" content="Bekijk alle modulaire units in de categorie Nieuw', false)
            ->assertDontSee('Archieven');
    }

    public function test_unit_composer_has_a_canonical_url_and_is_in_the_sitemap(): void
    {
        $this->get('/unit-samensteller/?c=abc')->assertOk()->assertSee('<link rel="canonical" href="'.url('unit-samensteller').'/">', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.url('unit-samensteller').'/</loc>', false);
    }

    public function test_seo_migration_fills_missing_meta_and_repairs_broken_links(): void
    {
        $rent = Page::factory()->create(['slug' => 'huren', 'title' => 'Huren']);
        Page::factory()->create(['slug' => 'containers', 'parent_id' => $rent->id]);
        $flexUnits = Page::factory()->create(['slug' => 'flex-units', 'parent_id' => $rent->id, 'meta_title' => 'Flex units', 'meta_description' => 'Met de schakelbare Flex-unit creëert u eenvoudig schaft-, kantoor-, sanitair- of vergaderruimte tot drie verdiepingen hoog.']);
        $contact = Page::factory()->create(['slug' => 'contact', 'meta_description' => 'Eigen tekst uit het CMS']);
        $storage = Page::factory()->create(['slug' => 'tijdelijke-opslag', 'content' => '<p>Bekijk onze <a href="/containers-huren/">containers</a>.</p>']);
        $post = Post::factory()->create(['content' => '<p><a href="/koop/">kopen</a> of <a href="/huur/">huren</a></p>']);
        $migration = require database_path('migrations/2026_10_30_111744_fill_missing_seo_meta_and_fix_broken_content_links.php');

        $migration->up();
        $migration->up();

        $this->assertSame('Flex units huren - ModulaireHuisvesting.nl', $flexUnits->refresh()->meta_title);
        $this->assertStringStartsWith('Flex units huren?', $flexUnits->meta_description);
        $this->assertSame('Eigen tekst uit het CMS', $contact->refresh()->meta_description);
        $this->assertSame('<p>Bekijk onze <a href="/huren/containers/">containers</a>.</p>', $storage->refresh()->content);
        $this->assertSame('<p><a href="/kopen/">kopen</a> of <a href="/huren/">huren</a></p>', $post->refresh()->content);
        $this->get('/containers-huren/')->assertStatus(301)->assertHeader('Location', url('huren/containers').'/');

        $migration->down();

        $this->assertSame('Flex units', $flexUnits->refresh()->meta_title);
        $this->assertStringStartsWith('Met de schakelbare Flex-unit', $flexUnits->meta_description);
        $this->assertDatabaseEmpty('redirects');
    }

    public function test_unknown_urls_return_404(): void
    {
        $this->get('/bestaat-niet/')->assertNotFound()->assertSee('Pagina niet gevonden');
    }

    public function test_encoded_slash_url_resolves_to_the_home_page(): void
    {
        Page::factory()->create(['title' => 'Home', 'slug' => '', 'content' => '<p>Welkom bij de modulaire units</p>']);

        $this->get('/%2f/')->assertOk()->assertSee('Welkom bij de modulaire units', false);
    }

    public function test_discount_popup_is_shown_when_a_code_is_set(): void
    {
        Page::factory()->create(['slug' => '']);
        Setting::put('discount_popup_code', 'ZOMER10');
        Setting::put('discount_popup_title', 'Zomerkorting');

        $this->get('/')->assertOk()->assertSee('id="mh-discount-popup"', false)->assertSee('ZOMER10')->assertSee('Zomerkorting');
    }

    public function test_discount_popup_is_hidden_without_a_code(): void
    {
        Page::factory()->create(['slug' => '']);

        $this->get('/')->assertOk()->assertDontSee('mh-discount-popup', false);
    }
}
