<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Unit;
use App\Models\User;
use App\Support\UnitConfiguration;
use App\Support\UnitRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnitConfiguratorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_create_a_configured_unit(): void
    {
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Kantoorunit 6x3',
            'status' => 'published',
            'configuration' => [
                'size' => '6x3',
                'walls' => [
                    'front' => ['window', 'window', 'door', 'empty', 'window', 'window'],
                    'back' => ['empty', 'empty', 'empty', 'empty', 'empty', 'empty'],
                    'left' => ['empty', 'window', 'empty'],
                    'right' => ['empty', 'empty', 'empty'],
                ],
                'colors' => ['cladding' => '#AABBCC', 'frame' => '#111111'],
                'background' => 'site',
            ],
        ])->assertSessionHasNoErrors();

        $unit = Unit::query()->where('slug', 'kantoorunit-6x3')->sole();
        $this->assertSame('6x3', $unit->configuration['size']);
        $this->assertSame(['window', 'window', 'door', 'empty', 'window', 'window'], $unit->configuration['walls']['front']);
        $this->assertSame(['empty', 'window', 'empty'], $unit->configuration['walls']['left']);
        $this->assertSame('#aabbcc', $unit->configuration['colors']['cladding']);
        $this->assertSame('site', $unit->configuration['background']);

        $this->actingAs($this->admin)->get(route('admin.units.edit', $unit))
            ->assertOk()
            ->assertSee('data-unit-configurator', false)
            ->assertSee('<svg', false);
    }

    public function test_unit_without_size_has_no_configuration(): void
    {
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Bestaande unit',
            'status' => 'published',
            'configuration' => ['size' => '', 'walls' => ['front' => ['door', 'empty', 'empty']]],
        ])->assertSessionHasNoErrors();

        $this->assertNull(Unit::query()->where('slug', 'bestaande-unit')->sole()->configuration);
    }

    public function test_slots_beyond_the_chosen_size_are_dropped(): void
    {
        $configuration = UnitConfiguration::normalize([
            'size' => '3x3',
            'walls' => ['front' => ['door', 'empty', 'window', 'window', 'window', 'window']],
        ]);

        $this->assertSame(['door', 'empty', 'window'], $configuration['walls']['front']);
        $this->assertSame(['empty', 'empty', 'empty'], $configuration['walls']['back']);
        $this->assertSame(UnitConfiguration::DEFAULT_COLORS, $configuration['colors']);
    }

    public function test_invalid_configuration_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Ongeldige unit',
            'status' => 'published',
            'configuration' => [
                'size' => '12x3',
                'walls' => ['front' => ['garage']],
                'colors' => ['cladding' => 'red'],
            ],
        ])->assertSessionHasErrors(['configuration.size', 'configuration.walls.front.0', 'configuration.colors.cladding']);
    }

    public function test_preview_renders_an_svg_for_admins_only(): void
    {
        $payload = ['configuration' => ['size' => '3x3', 'walls' => ['front' => ['empty', 'door', 'window']]]];

        $this->actingAs($this->admin)->post(route('admin.units.preview'), $payload)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('Modulaire unit van 3 x 3 meter', false);

        $this->actingAs(User::factory()->create())->post(route('admin.units.preview'), $payload)->assertForbidden();
    }

    public function test_renderer_draws_every_window_and_door(): void
    {
        $configuration = UnitConfiguration::normalize([
            'size' => '6x3',
            'walls' => [
                'front' => ['window', 'door', 'empty', 'empty', 'empty', 'window'],
                'back' => ['door', 'empty', 'empty', 'empty', 'empty', 'empty'],
                'left' => ['window', 'empty', 'empty'],
                'right' => ['empty', 'empty', 'empty'],
            ],
        ]);

        $svg = (new UnitRenderer)->render($configuration);

        $this->assertSame(3, substr_count($svg, 'fill="#c4def0"') + substr_count($svg, 'fill="#9fbfd2"'));
        $this->assertSame(2, substr_count($svg, 'fill="#d9d9d9"'));
    }

    public function test_renders_become_the_featured_image_of_a_unit_without_photo(): void
    {
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Kantoorunit',
            'status' => 'published',
            'configuration' => ['size' => '6x3', 'walls' => ['front' => ['door']]],
        ])->assertSessionHasNoErrors();

        $unit = Unit::query()->where('slug', 'kantoorunit')->sole();

        $this->assertCount(2, $unit->configuration_images);
        $this->assertSame($unit->configuration_images[0], $unit->featured_image);
        foreach ($unit->configuration_images as $path) {
            Storage::disk('public')->assertExists($path);
            $this->assertSame('image/svg+xml', Media::query()->where('path', $path)->sole()->mime_type);
        }

        $this->get($unit->url())
            ->assertOk()
            ->assertSee($unit->configuration_images[0])
            ->assertSee($unit->configuration_images[1])
            ->assertDontSee('og:image', false);
    }

    public function test_renders_are_added_after_the_photos_of_a_unit_with_photo(): void
    {
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Unit met foto',
            'status' => 'published',
            'featured_image_upload' => UploadedFile::fake()->image('unit.jpg', 1600, 1000),
            'configuration' => ['size' => '3x3'],
        ])->assertSessionHasNoErrors();

        $unit = Unit::query()->where('slug', 'unit-met-foto')->sole();

        $this->assertStringEndsWith('.jpg', $unit->featured_image);
        $this->assertSame(
            [$unit->featured_image, ...$unit->configuration_images],
            array_column($unit->images(), 'path'),
        );
    }

    public function test_changing_or_removing_the_configuration_replaces_the_renders(): void
    {
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Wisselende unit',
            'status' => 'published',
            'configuration' => ['size' => '3x3'],
        ]);
        $unit = Unit::query()->where('slug', 'wisselende-unit')->sole();
        $original = $unit->configuration_images;

        $this->actingAs($this->admin)->put(route('admin.units.update', $unit), [
            'title' => 'Wisselende unit',
            'status' => 'published',
            'configuration' => ['size' => '3x3', 'walls' => ['front' => ['window', 'window', 'window']]],
        ])->assertSessionHasNoErrors();
        $unit->refresh();

        $this->assertNotSame($original[0], $unit->configuration_images[0]);
        $this->assertSame($original[1], $unit->configuration_images[1]);
        $this->assertSame($unit->configuration_images[0], $unit->featured_image);
        Storage::disk('public')->assertMissing($original[0]);
        $this->assertDatabaseMissing('media', ['path' => $original[0]]);

        $this->actingAs($this->admin)->put(route('admin.units.update', $unit), [
            'title' => 'Wisselende unit',
            'status' => 'published',
            'configuration' => ['size' => ''],
        ])->assertSessionHasNoErrors();
        $unit->refresh();

        $this->assertNull($unit->configuration_images);
        $this->assertNull($unit->featured_image);
        Storage::disk('public')->assertMissing($original[1]);
    }

    public function test_command_regenerates_missing_renders(): void
    {
        $unit = Unit::factory()->create(['configuration' => UnitConfiguration::defaults('3x3')]);

        $this->artisan('units:render-configurations')->assertSuccessful();

        $unit->refresh();
        $this->assertCount(2, $unit->configuration_images);
        Storage::disk('public')->assertExists($unit->configuration_images);
    }
}
