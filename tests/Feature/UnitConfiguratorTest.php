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
                'colors' => ['cladding' => '#0e4c92', 'frame' => '#f2f0e8'],
                'background' => 'site',
            ],
        ])->assertSessionHasNoErrors();

        $unit = Unit::query()->where('slug', 'kantoorunit-6x3')->sole();
        $this->assertSame('6x3', $unit->configuration['size']);
        $this->assertSame(['window', 'window', 'door', 'empty', 'window', 'window'], $unit->configuration['walls']['front']);
        $this->assertSame(['empty', 'window', 'window'], $unit->configuration['walls']['left'], 'A window takes the free slot next to it.');
        $this->assertSame('#0e4c92', $unit->configuration['colors']['cladding']);
        $this->assertSame('#f2f0e8', $unit->configuration['colors']['frame']);
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

        $this->assertSame(['door', 'window', 'window'], $configuration['walls']['front']);
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
                'colors' => ['cladding' => '#aabbcc'],
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
                'front' => ['window', 'window', 'door', 'empty', 'window', 'window'],
                'back' => ['door', 'empty', 'empty', 'empty', 'empty', 'empty'],
                'left' => ['window', 'window', 'empty'],
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

    public function test_3d_photos_replace_the_drawings_when_enabled(): void
    {
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => '3D unit',
            'status' => 'published',
            'configuration' => ['size' => '6x3', 'walls' => ['front' => ['door']]],
            'photos_3d_enabled' => '1',
            'photos_3d' => $this->photos(),
        ])->assertSessionHasNoErrors();

        $unit = Unit::query()->where('slug', '3d-unit')->sole();

        $this->assertCount(2, $unit->configuration_images);
        $this->assertSame($unit->configuration_images[0], $unit->featured_image);
        $this->assertStringContainsString('3d-unit-front-3d-', $unit->configuration_images[0]);
        $this->assertStringContainsString('3d-unit-back-3d-', $unit->configuration_images[1]);
        foreach ($unit->configuration_images as $path) {
            Storage::disk('public')->assertExists($path);
            $media = Media::query()->where('path', $path)->sole();
            $this->assertSame('image/jpeg', $media->mime_type);
            $this->assertSame(1500, $media->width);
            Storage::disk('public')->assertExists($media->sizes['thumbnail']);
        }

        $this->actingAs($this->admin)->get(route('admin.units.edit', $unit))
            ->assertOk()
            ->assertSee('name="photos_3d_enabled" value="1" data-configurator-photos checked', false);
    }

    public function test_3d_photos_are_kept_until_the_configuration_changes(): void
    {
        $configuration = ['size' => '3x3', 'walls' => ['front' => ['door', 'empty', 'empty']]];
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Blijvende unit',
            'status' => 'published',
            'configuration' => $configuration,
            'photos_3d_enabled' => '1',
            'photos_3d' => $this->photos(),
        ]);
        $unit = Unit::query()->where('slug', 'blijvende-unit')->sole();
        $photos = $unit->configuration_images;

        $this->actingAs($this->admin)->put(route('admin.units.update', $unit), [
            'title' => 'Blijvende unit',
            'status' => 'published',
            'configuration' => $configuration,
            'photos_3d_enabled' => '1',
        ])->assertSessionHasNoErrors();
        $this->artisan('units:render-configurations')->assertSuccessful();

        $this->assertSame($photos, $unit->refresh()->configuration_images);

        $this->actingAs($this->admin)->put(route('admin.units.update', $unit), [
            'title' => 'Blijvende unit',
            'status' => 'published',
            'configuration' => ['size' => '3x3', 'walls' => ['front' => ['window', 'door', 'window']]],
            'photos_3d_enabled' => '1',
        ])->assertSessionHasNoErrors();
        $unit->refresh();

        $this->assertStringEndsWith('.svg', $unit->configuration_images[0]);
        $this->assertSame($unit->configuration_images[0], $unit->featured_image);
        Storage::disk('public')->assertMissing($photos);
        $this->assertDatabaseMissing('media', ['path' => $photos[0]]);
    }

    public function test_switching_3d_photos_off_brings_back_the_drawings(): void
    {
        $configuration = ['size' => '3x3'];
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Terug naar 2D',
            'status' => 'published',
            'configuration' => $configuration,
            'photos_3d_enabled' => '1',
            'photos_3d' => $this->photos(),
        ]);
        $unit = Unit::query()->where('slug', 'terug-naar-2d')->sole();

        $this->actingAs($this->admin)->put(route('admin.units.update', $unit), [
            'title' => 'Terug naar 2D',
            'status' => 'published',
            'configuration' => $configuration,
            'photos_3d' => $this->photos(),
        ])->assertSessionHasNoErrors();
        $unit->refresh();

        foreach ($unit->configuration_images as $path) {
            $this->assertStringEndsWith('.svg', $path);
        }
    }

    public function test_3d_photos_must_be_jpeg_images_of_known_views(): void
    {
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Ongeldige foto',
            'status' => 'published',
            'configuration' => ['size' => '3x3'],
            'photos_3d_enabled' => '1',
            'photos_3d' => ['front' => UploadedFile::fake()->create('front.pdf', 10, 'application/pdf'), 'top' => UploadedFile::fake()->image('top.jpg')],
        ])->assertSessionHasErrors(['photos_3d', 'photos_3d.front']);
    }

    public function test_coupled_units_share_front_and_back_and_extend_the_side_walls(): void
    {
        $configuration = UnitConfiguration::normalize(['size' => '6x3', 'modules' => '3']);

        $this->assertSame(3, $configuration['modules']);
        $this->assertCount(6, $configuration['walls']['front']);
        $this->assertCount(6, $configuration['walls']['back']);
        $this->assertCount(9, $configuration['walls']['left']);
        $this->assertCount(9, $configuration['walls']['right']);
        $this->assertSame(['length' => 6, 'depth' => 9, 'floors' => 1], UnitConfiguration::dimensions($configuration));
        $this->assertSame('3 gekoppelde units van 6 x 3 meter (6 x 9 meter)', UnitConfiguration::label($configuration));
        $this->assertSame(1, UnitConfiguration::normalize(['size' => '3x3'])['modules']);
        $this->assertSame(UnitConfiguration::MAX_MODULES, UnitConfiguration::normalize(['size' => '3x3', 'modules' => 99])['modules']);
    }

    public function test_admin_can_save_coupled_units(): void
    {
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Kantoor 6x6',
            'status' => 'published',
            'configuration' => [
                'size' => '6x3',
                'modules' => '2',
                'walls' => ['right' => ['empty', 'window', 'window', 'window', 'window', 'empty']],
            ],
        ])->assertSessionHasNoErrors();

        $unit = Unit::query()->where('slug', 'kantoor-6x6')->sole();
        $this->assertSame(2, $unit->configuration['modules']);
        $this->assertSame(['empty', 'window', 'window', 'window', 'window', 'empty'], $unit->configuration['walls']['right']);

        $this->actingAs($this->admin)->get(route('admin.units.edit', $unit))
            ->assertOk()
            ->assertSee('<option value="2" selected>2 units gekoppeld</option>', false)
            ->assertSee('Unit 2');

        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Te groot',
            'status' => 'published',
            'configuration' => ['size' => '6x3', 'modules' => UnitConfiguration::MAX_MODULES + 1],
        ])->assertSessionHasErrors('configuration.modules');
    }

    public function test_renderer_draws_coupled_units_with_their_seams(): void
    {
        $configuration = UnitConfiguration::normalize([
            'size' => '3x3',
            'modules' => 2,
            'walls' => ['right' => ['empty', 'window', 'empty', 'empty', 'window', 'empty']],
        ]);

        $svg = (new UnitRenderer)->renderView($configuration, 'front');

        $this->assertStringContainsString('aria-label="2 gekoppelde units van 3 x 3 meter (3 x 6 meter)"', $svg);
        $this->assertSame(2, substr_count($svg, 'fill="#9fbfd2"'));
    }

    public function test_a_second_layer_can_be_stacked_with_walls_of_its_own(): void
    {
        $single = UnitConfiguration::normalize(['size' => '6x3', 'modules' => 4]);
        $this->assertSame(1, $single['floors']);
        $this->assertArrayNotHasKey('upper_walls', $single);

        $stacked = UnitConfiguration::normalize([
            'size' => '6x3',
            'modules' => 4,
            'floors' => 2,
            'walls' => ['front' => ['door']],
            'upper_walls' => ['front' => ['window', 'window', 'window', 'window', 'window', 'window']],
        ]);

        $this->assertSame(2, $stacked['floors']);
        $this->assertSame('door', $stacked['walls']['front'][0]);
        $this->assertSame(array_fill(0, 6, 'window'), $stacked['upper_walls']['front']);
        $this->assertCount(12, $stacked['upper_walls']['left']);
        $this->assertSame('8 units van 6 x 3 meter in 2 lagen (6 x 12 meter)', UnitConfiguration::label($stacked));
        $this->assertSame(UnitConfiguration::MAX_FLOORS, UnitConfiguration::normalize(['size' => '3x3', 'floors' => 9])['floors']);

        $svg = (new UnitRenderer)->renderView($stacked, 'front');
        $this->assertSame(3, substr_count($svg, 'fill="#c4def0"'), 'Six window slots make three windows of 1.8 m.');
        $this->assertStringContainsString('aria-label="8 units van 6 x 3 meter in 2 lagen (6 x 12 meter)"', $svg);
    }

    public function test_stacked_units_get_outside_stairs_unless_turned_off(): void
    {
        $this->assertFalse(UnitConfiguration::normalize(['size' => '3x3', 'stairs' => true])['stairs']);
        $this->assertTrue(UnitConfiguration::normalize(['size' => '3x3', 'floors' => 2])['stairs']);
        $this->assertFalse(UnitConfiguration::normalize(['size' => '3x3', 'floors' => 2, 'stairs' => '0'])['stairs']);

        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Zonder trap',
            'status' => 'published',
            'configuration' => ['size' => '3x3', 'floors' => '2', 'stairs' => '0'],
        ])->assertSessionHasNoErrors();

        $unit = Unit::query()->where('slug', 'zonder-trap')->sole();
        $this->assertFalse($unit->configuration['stairs']);

        $this->actingAs($this->admin)->get(route('admin.units.edit', $unit))
            ->assertOk()
            ->assertSee('Buitentrap naar de deuren op de verdieping')
            ->assertDontSee('data-configurator-stairs checked', false);
    }

    public function test_admin_can_save_stacked_units(): void
    {
        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Gestapeld kantoor',
            'status' => 'published',
            'configuration' => [
                'size' => '3x3',
                'modules' => '2',
                'floors' => '2',
                'upper_walls' => ['front' => ['window', 'window', 'empty']],
            ],
        ])->assertSessionHasNoErrors();

        $unit = Unit::query()->where('slug', 'gestapeld-kantoor')->sole();
        $this->assertSame(2, $unit->configuration['floors']);
        $this->assertSame(['window', 'window', 'empty'], $unit->configuration['upper_walls']['front']);

        $this->actingAs($this->admin)->get(route('admin.units.edit', $unit))
            ->assertOk()
            ->assertSee('<option value="2" selected>2 lagen (gestapeld)</option>', false)
            ->assertSee('Verdieping');

        $this->actingAs($this->admin)->post(route('admin.units.store'), [
            'title' => 'Te hoog',
            'status' => 'published',
            'configuration' => ['size' => '3x3', 'floors' => 3],
        ])->assertSessionHasErrors('configuration.floors');
    }

    public function test_windows_take_two_slots_within_one_unit(): void
    {
        $pair = fn (array $right, int $modules = 1): array => UnitConfiguration::normalize(['size' => '3x3', 'modules' => $modules, 'walls' => ['right' => $right]])['walls']['right'];

        $this->assertSame(['window', 'window', 'empty'], $pair(['window', 'empty', 'empty']));
        $this->assertSame(['empty', 'window', 'window'], $pair(['empty', 'empty', 'window']));
        $this->assertSame(['empty', 'door', 'empty'], $pair(['window', 'door', 'empty']), 'A window without a free neighbour is dropped.');
        $this->assertSame(['empty', 'window', 'window', 'empty', 'empty', 'empty'], $pair(['empty', 'empty', 'window', 'empty', 'empty', 'empty'], 2), 'A window never crosses the seam between units.');
    }

    public function test_three_window_slots_make_one_window_in_the_middle(): void
    {
        [$window] = UnitConfiguration::openings(['window', 'window', 'window'], 3);
        $this->assertEqualsWithDelta(0.6, $window['left'], 0.001);
        $this->assertEqualsWithDelta(2.4, $window['right'], 0.001);

        $this->assertSame([2, 3], UnitConfiguration::windowSpans(5));
        $this->assertSame([2, 2], UnitConfiguration::windowSpans(4));
        $this->assertCount(2, UnitConfiguration::openings(['window', 'window', 'window', 'window', 'window', 'empty'], 6));

        $configuration = UnitConfiguration::normalize(['size' => '6x3', 'walls' => ['left' => ['window', 'window', 'window']]]);
        $this->assertSame(['window', 'window', 'window'], $configuration['walls']['left']);
        $svg = (new UnitRenderer)->renderView($configuration, 'back');
        $this->assertSame(1, substr_count($svg, 'fill="#9fbfd2"'));
    }

    public function test_windows_and_doors_have_their_real_sizes(): void
    {
        $this->assertSame(['width' => 1.8, 'height' => 1.2, 'bottom' => 0.9, 'slots' => 2], UnitConfiguration::OPENING_SIZES['window']);
        $this->assertSame(0.875, UnitConfiguration::OPENING_SIZES['door']['width']);
        $this->assertSame(2.125, UnitConfiguration::OPENING_SIZES['door']['height']);
        $this->assertSame(['width' => 0.811, 'height' => 2.093], UnitConfiguration::DOOR_CLEAR);

        [$first, $second] = UnitConfiguration::openings(['empty', 'window', 'window', 'window', 'window', 'empty'], 6);
        $this->assertEqualsWithDelta(['left' => 1.1, 'right' => 2.9], ['left' => $first['left'], 'right' => $first['right']], 0.001);
        $this->assertEqualsWithDelta(['left' => 3.1, 'right' => 4.9], ['left' => $second['left'], 'right' => $second['right']], 0.001);

        [$door] = UnitConfiguration::openings(['door', 'empty', 'empty'], 3);
        $this->assertEqualsWithDelta(UnitConfiguration::POST_CLEARANCE, $door['left'], 0.001, 'A door in a corner slot keeps clear of the corner post.');
        $this->assertEqualsWithDelta(2.275, $door['top'], 0.001);
    }

    public function test_only_white_anthracite_blue_and_logo_blue_are_available(): void
    {
        $this->assertSame(['Wit', 'Antraciet', 'Blauw', 'Logoblauw'], array_values(UnitConfiguration::COLORS));
        $this->assertSame('#4188aa', UnitConfiguration::normalize(['size' => '3x3', 'colors' => ['cladding' => '#4a8db0']])['colors']['cladding']);

        $configuration = UnitConfiguration::normalize(['size' => '3x3', 'colors' => ['cladding' => '#3d4a52', 'frame' => '#2255aa']]);

        $this->assertSame('#383e42', $configuration['colors']['cladding']);
        $this->assertSame('#0e4c92', $configuration['colors']['frame']);
    }

    public function test_new_configurations_stand_on_an_industrial_estate(): void
    {
        $this->assertSame('industrial', UnitConfiguration::defaults()['background']);
        $this->assertArrayHasKey('industrial', UnitRenderer::BACKDROPS);
        $this->assertSame('meadow', UnitConfiguration::normalize(['size' => '3x3', 'background' => 'meadow'])['background']);
    }

    /**
     * @return array<string, UploadedFile>
     */
    private function photos(): array
    {
        return collect(UnitRenderer::VIEWS)->keys()
            ->mapWithKeys(fn (string $view): array => [$view => UploadedFile::fake()->image($view.'.jpg', 1500, 1000)])
            ->all();
    }
}
