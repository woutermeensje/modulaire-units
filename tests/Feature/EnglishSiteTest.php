<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnglishSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_pages_resolve_by_their_translated_path(): void
    {
        $parent = $this->translatedPage(['slug' => 'kopen', 'title' => 'Kopen'], ['title' => 'Buy', 'slug' => 'buy']);
        $this->translatedPage(
            ['slug' => 'bouwunits', 'parent_id' => $parent->id, 'title' => 'Bouwunits', 'content' => '<p>Bouwunits kopen</p>'],
            ['title' => 'Site cabins', 'slug' => 'site-cabins', 'content' => '<p>Buy site cabins</p>'],
        );

        $this->get('/en/buy/site-cabins/')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Buy site cabins', false)
            ->assertDontSee('Bouwunits kopen', false);

        $this->get('/kopen/bouwunits/')->assertOk()->assertSee('Bouwunits kopen', false);
        $this->get('/en/kopen/bouwunits/')->assertNotFound();
    }

    public function test_the_translated_path_follows_a_changed_parent_slug(): void
    {
        $parent = $this->translatedPage(['slug' => 'huren', 'title' => 'Huren'], ['title' => 'Rent', 'slug' => 'rent']);
        $child = $this->translatedPage(['slug' => 'kantines', 'parent_id' => $parent->id, 'title' => 'Kantines'], ['title' => 'Canteens', 'slug' => 'canteens']);

        $parent->setTranslations('en', ['slug' => 'hire'])->save();

        $this->assertSame('hire/canteens', $child->fresh()->translation('en', 'path'));
        $this->assertSame('/en/hire/canteens/', Locale::using('en', fn (): string => $child->fresh()->url()));
    }

    public function test_untranslated_pages_do_not_exist_in_english(): void
    {
        Page::factory()->create(['slug' => 'contact']);

        $this->get('/en/contact/')->assertNotFound();
    }

    public function test_pages_link_to_each_other_with_hreflang(): void
    {
        $this->translatedPage(['slug' => 'over-ons', 'title' => 'Over ons'], ['title' => 'About us', 'slug' => 'about-us']);

        $this->get('/over-ons/')
            ->assertSee('<link rel="alternate" hreflang="nl" href="http://localhost:8000/over-ons/">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="http://localhost:8000/en/about-us/">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="http://localhost:8000/over-ons/">', false)
            ->assertSee('<meta property="og:locale" content="nl_NL">', false);

        $this->get('/en/about-us/')
            ->assertSee('<link rel="canonical" href="http://localhost:8000/en/about-us/">', false)
            ->assertSee('<meta property="og:locale" content="en_GB">', false)
            ->assertSee('<a href="/over-ons/" class="mh-header__login mh-header__language" hreflang="nl"', false);
    }

    public function test_dutch_links_in_content_and_menus_point_to_the_english_page(): void
    {
        $this->translatedPage(['slug' => 'contact', 'title' => 'Contact'], ['title' => 'Contact', 'slug' => 'contact-us']);
        Page::factory()->create(['slug' => 'vacatures']);
        $this->translatedPage(
            ['slug' => 'over-ons', 'title' => 'Over ons'],
            ['title' => 'About us', 'slug' => 'about-us', 'content' => '<p><a href="/contact/#form">Contact</a> <a href="https://example.com/contact/">Extern</a> <a href="/vacatures/">Jobs</a></p>'],
        );
        MenuItem::factory()->create(['menu' => 'main', 'label' => 'Contact', 'url' => '/contact/', 'translations' => ['en' => ['label' => 'Get in touch']]]);

        $this->get('/en/about-us/')
            ->assertSee('<a href="/en/contact-us/#form">Contact</a>', false)
            ->assertSee('<a href="https://example.com/contact/">Extern</a>', false)
            ->assertSee('<a href="/vacatures/">Jobs</a>', false)
            ->assertSee('href="/en/contact-us/">Get in touch</a>', false)
            ->assertSee('href="/en/my-quote/"', false);
    }

    public function test_units_and_posts_use_their_translated_slug(): void
    {
        Unit::factory()->create(['title' => 'Kantoorunit 6x3', 'slug' => 'kantoorunit-6x3', 'translations' => ['en' => ['title' => 'Office unit 6x3', 'slug' => 'office-unit-6x3']]]);
        Post::factory()->create(['title' => 'Wat is modulair bouwen?', 'slug' => 'wat-is-modulair-bouwen', 'translations' => ['en' => ['title' => 'What is modular construction?', 'slug' => 'what-is-modular-construction']]]);

        $this->get('/en/units/office-unit-6x3/')->assertOk()->assertSee('Office unit 6x3');
        $this->get('/en/units/kantoorunit-6x3/')->assertNotFound();
        $this->get('/en/what-is-modular-construction/')->assertOk()->assertSee('What is modular construction?');
        $this->get('/en/units/')->assertOk()->assertSee('/en/units/office-unit-6x3/', false);
    }

    public function test_the_sitemap_lists_both_languages_with_alternates(): void
    {
        $this->translatedPage(['slug' => 'over-ons', 'title' => 'Over ons'], ['title' => 'About us', 'slug' => 'about-us']);
        Page::factory()->create(['slug' => 'alleen-nederlands']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>http://localhost:8000/en/about-us/</loc>', false)
            ->assertSee('<xhtml:link rel="alternate" hreflang="en" href="http://localhost:8000/en/about-us/"/>', false)
            ->assertSee('<loc>http://localhost:8000/alleen-nederlands/</loc>', false)
            ->assertDontSee('/en/alleen-nederlands/', false);
    }

    public function test_settings_have_an_english_text(): void
    {
        Setting::put('footer_about', 'Wij leveren units.');
        Setting::put('footer_about_en', 'We supply units.');
        $this->translatedPage(['slug' => '', 'title' => 'Home'], ['title' => 'Home', 'slug' => '']);

        $this->get('/en/')->assertOk()->assertSee('We supply units.')->assertDontSee('Wij leveren units.');
        $this->get('/')->assertOk()->assertSee('Wij leveren units.');
    }

    public function test_redirects_only_apply_to_the_dutch_site(): void
    {
        Redirect::factory()->create(['from_path' => '/oud/', 'to_path' => '/nieuw/']);

        $this->get('/oud/')->assertRedirect();
        $this->get('/en/oud/')->assertNotFound();
    }

    public function test_admins_edit_the_english_translation_of_a_page(): void
    {
        $page = Page::factory()->create(['title' => 'Over ons', 'slug' => 'over-ons']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.pages.update', $page), [
                'title' => 'Over ons',
                'slug' => 'over-ons',
                'template' => 'default',
                'status' => 'published',
                'translations' => ['en' => ['title' => 'About us', 'slug' => '', 'meta_description' => 'Who we are']],
            ])
            ->assertRedirect();

        $page->refresh();
        $this->assertSame('Over ons', $page->title);
        $this->assertSame('about-us', $page->translation('en', 'slug'));
        $this->assertSame('about-us', $page->translation('en', 'path'));
        $this->assertSame('Who we are', $page->translation('en', 'meta_description'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $english
     */
    private function translatedPage(array $attributes, array $english): Page
    {
        return Page::factory()->create($attributes + ['translations' => ['en' => $english]]);
    }
}
