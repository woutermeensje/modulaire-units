<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Category;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\Unit;
use App\Models\UnitTerm;
use App\Models\User;
use App\Services\SiteContentSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteContentTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_contains_website_content_and_excludes_accounts_submissions_and_private_settings(): void
    {
        User::factory()->admin()->create(['email' => 'private-account@example.com']);
        Submission::factory()->create(['email' => 'private-submission@example.com']);
        Setting::factory()->create(['key' => 'notification_email', 'value' => 'private-notifications@example.com']);
        Setting::factory()->create(['key' => 'api_secret', 'value' => 'private-secret']);
        Setting::factory()->create(['key' => 'site_name', 'value' => 'Mijn lokale website']);
        Page::factory()->create(['slug' => '', 'content' => '<p>Actuele lokale inhoud</p>']);

        $path = $this->snapshotPath();

        $this->artisan('site:export', ['file' => $path])->assertSuccessful();

        $json = File::get($path);
        $snapshot = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(SiteContentSnapshot::TABLES, array_keys($snapshot['tables']));
        $this->assertSame('<p>Actuele lokale inhoud</p>', $snapshot['tables']['pages'][0]['content']);
        $this->assertSame('Mijn lokale website', $snapshot['tables']['settings'][0]['value']);
        $this->assertIsBool($snapshot['tables']['pages'][0]['show_title']);
        $this->assertStringNotContainsString('private-', $json);
        $this->assertStringNotContainsString('password', $json);
    }

    public function test_import_restores_current_content_and_relationships_without_calling_wordpress(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $submission = Submission::factory()->create();
        Page::factory()->create(['slug' => '', 'content' => '<p>Actuele homepage</p>']);
        $parent = Page::factory()->create(['slug' => 'huren']);
        Page::factory()->create(['slug' => 'kantoorunits', 'parent_id' => $parent->id]);
        $menu = MenuItem::factory()->create(['label' => 'Huren', 'url' => '/huren/']);
        MenuItem::factory()->create(['label' => 'Kantoorunits', 'parent_id' => $menu->id, 'url' => '/huren/kantoorunits/']);
        $author = Author::factory()->create();
        $category = Category::factory()->create();
        Post::factory()->create(['author_id' => $author->id, 'category_id' => $category->id]);
        $term = UnitTerm::factory()->create();
        $unit = Unit::factory()->create();
        $unit->terms()->attach($term);
        Media::factory()->create(['path' => 'media/lokaal.jpg', 'sizes' => ['medium' => 'media/lokaal-300x200.jpg']]);
        Setting::put('phone', '010 123 4567');

        $path = $this->snapshotPath();
        $this->artisan('site:export', ['file' => $path])->assertSuccessful();
        $this->clearContent();

        Setting::put('phone', 'Oud telefoonnummer');
        Setting::put('notification_email', 'production@example.com');
        $this->assertSame('Oud telefoonnummer', Setting::get('phone'));

        $this->artisan('site:import', ['file' => $path])->assertSuccessful();

        $this->assertSame('010 123 4567', Setting::get('phone'));
        $this->assertSame('production@example.com', Setting::get('notification_email'));
        $this->assertModelExists($user);
        $this->assertModelExists($submission);
        $this->assertSame(Page::query()->where('path', 'huren')->sole()->id, Page::query()->where('path', 'huren/kantoorunits')->sole()->parent_id);
        $this->assertSame(MenuItem::query()->where('label', 'Huren')->sole()->id, MenuItem::query()->where('label', 'Kantoorunits')->sole()->parent_id);
        $this->assertSame($author->slug, Post::query()->sole()->author->slug);
        $this->assertSame($category->slug, Post::query()->sole()->category->slug);
        $this->assertSame($term->slug, Unit::query()->sole()->terms->sole()->slug);
        $this->assertSame(['medium' => 'media/lokaal-300x200.jpg'], Media::query()->sole()->sizes);
        $this->get('/')->assertOk()->assertSee('Actuele homepage')->assertSee('Kantoorunits');
        $this->get('/huren/kantoorunits/')->assertOk();
        Http::assertNothingSent();
    }

    public function test_import_refuses_to_overwrite_existing_content(): void
    {
        Page::factory()->create(['slug' => '', 'content' => '<p>Gewijzigd op productie</p>']);
        $path = $this->snapshotPath();
        $this->artisan('site:export', ['file' => $path])->assertSuccessful();

        $this->artisan('site:import', ['file' => $path])->assertFailed();

        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseHas('pages', ['content' => '<p>Gewijzigd op productie</p>']);
    }

    public function test_if_empty_option_leaves_existing_content_unchanged_on_redeployment(): void
    {
        Page::factory()->create(['slug' => '', 'title' => 'Productie']);

        $this->artisan('site:import', ['--if-empty' => true])->assertSuccessful();

        $this->assertDatabaseCount('pages', 1);
        $this->assertDatabaseHas('pages', ['title' => 'Productie']);
    }

    public function test_invalid_foreign_key_rolls_back_all_imported_content(): void
    {
        Media::factory()->create();
        Page::factory()->create(['slug' => 'kind']);
        $snapshot = app(SiteContentSnapshot::class)->export();
        $snapshot['tables']['pages'][0]['parent_id'] = 999999;
        $path = $this->snapshotPath();
        File::put($path, json_encode($snapshot, JSON_THROW_ON_ERROR));
        $this->clearContent();

        $this->artisan('site:import', ['file' => $path])->assertFailed();

        $this->assertDatabaseEmpty('pages');
        $this->assertDatabaseEmpty('media');
    }

    public function test_import_rejects_unexpected_tables_before_changing_the_database(): void
    {
        $snapshot = app(SiteContentSnapshot::class)->export();
        $snapshot['tables']['users'] = [];
        $path = $this->snapshotPath();
        File::put($path, json_encode($snapshot, JSON_THROW_ON_ERROR));

        $this->artisan('site:import', ['file' => $path])->assertFailed();

        $this->assertDatabaseEmpty('pages');
        $this->assertDatabaseEmpty('users');
    }

    public function test_missing_or_invalid_export_fails_without_creating_content(): void
    {
        $path = $this->snapshotPath();
        $this->artisan('site:import', ['file' => $path])->assertFailed();

        File::put($path, 'geen geldige JSON');
        $this->artisan('site:import', ['file' => $path])->assertFailed();

        $this->assertDatabaseEmpty('pages');
    }

    public function test_bundled_local_content_renders_the_homepage_navigation_and_offer(): void
    {
        Http::preventStrayRequests();

        $this->artisan('site:import')->assertSuccessful();

        $this->get('/')->assertOk()->assertSee('menu-hoofdmenu', false)->assertSee('Huren');
        $this->get('/aanbod/')->assertOk();
        $this->assertDatabaseCount('pages', 54);
        $this->assertDatabaseCount('menu_items', 53);
        $this->assertDatabaseCount('units', 3);
        Http::assertNothingSent();
    }

    private function snapshotPath(): string
    {
        Storage::fake('local');

        return Storage::disk('local')->path('site-content.json');
    }

    private function clearContent(): void
    {
        foreach (array_reverse(SiteContentSnapshot::TABLES) as $table) {
            DB::table($table)->delete();
        }
    }
}
