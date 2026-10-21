<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use App\Services\MediaUploader;
use App\Support\SiteUrl;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CloudMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cloud_storage_urls_are_used_for_hero_embedded_images_srcset_and_page_css(): void
    {
        $this->fakeBucket('public');
        $page = Page::factory()->create([
            'slug' => '',
            'template' => 'landing',
            'hero' => ['image' => 'media/hero.jpg', 'title' => 'Lokale homepage'],
            'content' => '<img src="/storage/media/foto.jpg" srcset="/storage/media/foto-small.jpg 300w, /storage/media/foto.jpg 1200w">'
                .'<img src="https://other.example.com/storage/media/external.jpg">',
            'custom_css' => '.banner { background-image: url("/storage/media/banner.jpg"); }',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('https://images.example.com/media/hero.jpg', false)
            ->assertSee('src="https://images.example.com/media/foto.jpg"', false)
            ->assertSee('srcset="https://images.example.com/media/foto-small.jpg 300w, https://images.example.com/media/foto.jpg 1200w"', false)
            ->assertSee('url("https://images.example.com/media/banner.jpg")', false)
            ->assertSee('https://other.example.com/storage/media/external.jpg', false);

        $this->assertStringContainsString('/storage/media/foto.jpg', $page->fresh()->content);
        $this->assertSame('https://images.example.com/media/foto.jpg', SiteUrl::media('/storage/media/foto.jpg'));
    }

    public function test_old_wordpress_upload_urls_redirect_to_the_public_bucket(): void
    {
        $this->fakeBucket('public');

        $this->get('/wp-content/uploads/2026/05/foto.png')
            ->assertRedirect('https://images.example.com/media/2026/05/foto.png');
    }

    public function test_local_media_urls_keep_working_without_a_bucket(): void
    {
        $this->assertSame('/storage/media/foto.jpg', SiteUrl::media('media/foto.jpg'));
        $this->assertSame('/images/logo.svg', SiteUrl::media('/images/logo.svg'));
        $this->assertNull(SiteUrl::media(null));
    }

    public function test_image_variants_can_be_generated_on_storage_without_local_file_paths(): void
    {
        $file = UploadedFile::fake()->image('foto.jpg', 1600, 1000);
        $media = Media::factory()->create(['path' => 'media/foto.jpg']);
        $variants = [];
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('get')->once()->with('media/foto.jpg')->andReturn(file_get_contents($file->getPathname()));
        $disk->shouldNotReceive('path');
        $disk->shouldReceive('put')->times(4)->andReturnUsing(function (string $path, string $contents) use (&$variants): bool {
            $variants[$path] = getimagesizefromstring($contents);

            return true;
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        app(MediaUploader::class)->generateSizes($media);

        $sizes = $media->fresh()->sizes;
        $this->assertCount(4, $sizes);
        $this->assertSame([300, 188], array_slice($variants[$sizes['medium']], 0, 2));
        $this->assertSame([150, 150], array_slice($variants[$sizes['thumbnail']], 0, 2));
    }

    public function test_upload_transfers_local_files_and_can_resume_without_overwriting_existing_files(): void
    {
        $source = Storage::fake('local');
        $source->put('media/2026/foto.jpg', 'lokale afbeelding');
        $source->put('media/2026/al-aanwezig.jpg', 'dezelfde afbeelding');
        $target = $this->fakeBucket('s3');
        $target->put('media/2026/al-aanwezig.jpg', 'dezelfde afbeelding');

        $this->artisan('site:upload-media', ['--source' => $source->path('media')])->assertSuccessful();

        $target->assertExists('media/2026/foto.jpg');
        $this->assertSame('lokale afbeelding', $target->get('media/2026/foto.jpg'));
        $this->assertSame('dezelfde afbeelding', $target->get('media/2026/al-aanwezig.jpg'));

        $this->artisan('site:upload-media', ['--source' => $source->path('media')])
            ->expectsOutputToContain('Al aanwezig')->assertSuccessful();
    }

    public function test_overwrite_option_uploads_changed_files_with_the_same_size(): void
    {
        $source = Storage::fake('local');
        $source->put('media/foto.jpg', 'nieuw');
        $target = $this->fakeBucket('s3');
        $target->put('media/foto.jpg', 'ouder');

        $this->artisan('site:upload-media', ['--source' => $source->path('media'), '--overwrite' => true])
            ->assertSuccessful();

        $this->assertSame('nieuw', $target->get('media/foto.jpg'));
    }

    public function test_failed_upload_returns_failure_instead_of_reporting_success(): void
    {
        $source = Storage::fake('local');
        $source->put('media/foto.jpg', 'afbeelding');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->with('media/foto.jpg')->andReturnFalse();
        $disk->shouldReceive('put')->with('media/foto.jpg', Mockery::type('resource'))->andReturnFalse();
        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $this->artisan('site:upload-media', ['--source' => $source->path('media')])->assertFailed();
    }

    public function test_upload_requires_an_s3_destination(): void
    {
        $this->artisan('site:upload-media', ['--disk' => 'local'])->assertFailed();
    }

    public function test_wordpress_media_import_can_write_to_object_storage(): void
    {
        $this->app->useStoragePath(Storage::fake('local')->path('wordpress-import'));
        $target = $this->fakeBucket('public');
        Http::preventStrayRequests();
        Http::fake([
            'https://wordpress.example.com/wp-json/wp/v2/media*' => Http::response([[
                'id' => 123,
                'source_url' => 'https://wordpress.example.com/wp-content/uploads/2026/foto.jpg',
                'title' => ['rendered' => 'Lokale afbeelding'],
                'alt_text' => 'Een unit',
                'mime_type' => 'image/jpeg',
            ]]),
            'https://wordpress.example.com/wp-json/wp/v2/*' => Http::response([]),
            'https://wordpress.example.com/wp-json/' => Http::response(['name' => 'Voorbeeld']),
            'https://wordpress.example.com/wp-content/uploads/*' => Http::response('afbeeldingsbestand'),
            'https://wordpress.example.com/*' => Http::response('<html><body><footer></footer></body></html>'),
        ]);

        $this->artisan('wp:import', ['--source' => 'https://wordpress.example.com', '--fresh' => true])
            ->assertSuccessful();

        $this->assertSame('afbeeldingsbestand', $target->get('media/2026/foto.jpg'));
        $this->assertDatabaseHas('media', ['wp_id' => 123, 'path' => 'media/2026/foto.jpg']);
        $this->assertFileDoesNotExist(storage_path('app/wp-import/downloads/media/2026/foto.jpg'));
    }

    private function fakeBucket(string $name): FilesystemAdapter
    {
        $disk = Storage::fake($name, ['url' => 'https://images.example.com']);
        config(["filesystems.disks.{$name}.driver" => 's3']);

        return $disk;
    }
}
