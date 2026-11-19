<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_name_is_left_out_of_a_title_that_would_get_too_long(): void
    {
        $this->assertSame('Contact - ModulaireHuisvesting.nl', Seo::withSiteName('Contact'));
        $this->assertSame(
            'Tijdelijke huisvesting tijdens een verbouwing nodig?',
            Seo::withSiteName('Tijdelijke huisvesting tijdens een verbouwing nodig?'),
        );
    }

    public function test_sitemap_leaves_out_redirected_pages_and_lists_the_shop(): void
    {
        Page::factory()->create(['slug' => 'aanbod', 'content' => '']);
        Page::factory()->create(['slug' => 'modulaire-units']);
        Redirect::factory()->create(['from_path' => '/aanbod', 'to_path' => '/modulaire-units/']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.url('modulaire-units').'/</loc>', false)
            ->assertDontSee('<loc>'.url('aanbod').'/</loc>', false)
            ->assertSee('<loc>'.url('winkel').'/</loc>', false);
    }

    public function test_blog_archives_per_author_and_category_are_not_indexed(): void
    {
        $author = Author::factory()->create(['slug' => 'nick']);
        $category = Category::factory()->create(['slug' => 'uncategorized']);
        Post::factory()->create(['author_id' => $author->id, 'category_id' => $category->id]);

        $this->get('/author/nick/')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/category/uncategorized/')->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_a_subpage_with_a_hero_has_the_heading_of_its_content_as_only_h1(): void
    {
        Page::factory()->create([
            'slug' => 'inrichting',
            'template' => 'landing',
            'hero' => ['title' => 'Modulaire Units en Oplossingen voor Tijdelijke Huisvesting'],
            'content' => '<h1>Modulaire units inrichten?</h1><p>Tekst</p><h1>Inrichting op maat</h1>',
        ]);

        $content = $this->get('/inrichting/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, '<h1'));
        $this->assertStringContainsString('<h1>Modulaire units inrichten?</h1>', $content);
        $this->assertStringContainsString('<p class="mh-home-hero__title">Modulaire Units en Oplossingen voor Tijdelijke Huisvesting</p>', $content);
    }

    public function test_a_page_without_any_heading_shows_its_title_as_h1(): void
    {
        Page::factory()->create(['slug' => 'offerte-aanvragen', 'title' => 'Offerte aanvragen', 'content' => '<p>Formulier</p>']);

        $content = $this->get('/offerte-aanvragen/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, '<h1'));
        $this->assertStringContainsString('<h1 class="entry-title">Offerte aanvragen</h1>', $content);
    }

    public function test_the_home_page_hero_title_is_the_only_h1(): void
    {
        Page::factory()->create([
            'title' => 'Home',
            'slug' => '',
            'template' => 'landing',
            'hero' => ['title' => 'Modulaire units, koop en huur'],
            'content' => '<h1>Modulaire Huisvesting</h1><p>Intro</p><h2>Huren</h2><p>Tekst</p><h2>Kopen</h2><p>Tekst</p>',
        ]);

        $content = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, '<h1'));
        $this->assertStringContainsString('<h1 class="mh-home-hero__title">Modulaire units, koop en huur</h1>', $content);
        $this->assertStringContainsString('<h2 id="mh-home-blocks-title" class="mh-home-blocks__title">Modulaire Huisvesting</h2>', $content);
    }

    public function test_a_blog_post_has_its_title_as_only_h1(): void
    {
        Post::factory()->create(['slug' => 'tweedehands-kantoorunits', 'content' => '<h1>Kop uit WordPress</h1><p>Tekst</p><h1></h1>']);

        $content = $this->get('/tweedehands-kantoorunits/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, '<h1'));
        $this->assertStringContainsString('<h2>Kop uit WordPress</h2>', $content);
    }

    public function test_migration_takes_empty_pages_offline_and_repairs_internal_links(): void
    {
        $planning = Page::factory()->create(['slug' => 'planning', 'content' => '<p>&nbsp;</p>']);
        $berichten = Page::factory()->create(['slug' => 'berichten', 'content' => '<p>Inmiddels wel geschreven.</p>']);
        $aanbod = Page::factory()->create(['slug' => 'aanbod', 'content' => '']);
        Redirect::factory()->create(['from_path' => '/aanbod', 'to_path' => '/modulaire-units/']);
        $used = Page::factory()->create(['slug' => 'gebruikt', 'content' => '<p>Lees <a href="http://Waar kun je tweedehands kantoorunits kopen?" data-wplink-url-error="true">dit artikel</a>.</p>']);
        $advice = Page::factory()->create(['slug' => 'advies', 'content' => '<a class="btn" href="/aanbod/">Aanbod bekijken</a>']);
        $other = MenuItem::query()->create(['menu' => 'footer', 'label' => 'OVERIGE', 'sort' => 0]);
        $migration = require database_path('migrations/2026_11_19_055652_remove_ghost_pages_and_fix_broken_internal_links.php');

        $migration->up();
        $migration->up();

        $this->assertSame('draft', $planning->refresh()->status);
        $this->assertSame('published', $berichten->refresh()->status);
        $this->assertSame('draft', $aanbod->refresh()->status);
        $this->get('/planning/')->assertStatus(301)->assertHeader('Location', url('werkwijze').'/');
        $this->assertSame('<p>Lees <a href="/waar-kun-je-tweedehands-kantoorunits-kopen/" data-wplink-url-error="true">dit artikel</a>.</p>', $used->refresh()->content);
        $this->assertSame('<a class="btn" href="/modulaire-units/">Aanbod bekijken</a>', $advice->refresh()->content);
        $this->assertSame(['/werkwijze/'], $other->children()->pluck('url')->all());

        $migration->down();

        $this->assertSame('published', $planning->refresh()->status);
        $this->assertFalse(Redirect::query()->where('from_path', '/planning')->exists());
        $this->assertTrue(Redirect::query()->where('from_path', '/aanbod')->exists());
        $this->assertSame(0, $other->children()->count());
    }
}
