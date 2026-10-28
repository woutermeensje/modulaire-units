<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Support\UnitConfiguration;
use App\Support\UnitRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitConfiguratorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

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
}
