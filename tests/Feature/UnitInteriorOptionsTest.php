<?php

namespace Tests\Feature;

use App\Models\Submission;
use App\Support\ComposedUnit;
use App\Support\FloorPlanRenderer;
use App\Support\InteriorLayout;
use App\Support\UnitConfiguration;
use App\Support\UnitRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UnitInteriorOptionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The request of Heras B.V.: a 6x3 gatehouse in anthracite with four windows, a door and a wind- and watertight
     * hatch for handing out access passes, heating and air conditioning, a counter with a chair, sockets, a network
     * point, a step at the door and a toilet that is not certain yet.
     *
     * @var array<string, mixed>
     */
    private const HERAS = [
        'size' => '6x3',
        'modules' => 1,
        'walls' => [
            'front' => ['window', 'window', 'hatch', 'empty', 'window', 'window'],
            'back' => ['empty', 'window', 'window', 'window', 'window', 'empty'],
            'right' => ['empty', 'door', 'empty'],
            'left' => ['empty', 'empty', 'empty'],
        ],
        'colors' => ['cladding' => '#383e42', 'frame' => '#383e42'],
        'interior' => ['airco', 'heater', 'toilet'],
        'furniture' => ['counter' => 1, 'chair' => 1, 'desk' => 0],
        'connections' => ['socket' => 4, 'network' => 1],
        'tentative' => ['toilet'],
        'steps' => '1',
        'usage' => ['portiersloge'],
    ];

    public function test_furniture_and_connections_keep_their_numbers_within_limits(): void
    {
        $configuration = UnitConfiguration::normalize([
            'size' => '6x3',
            'furniture' => ['desk' => 2, 'chair' => 0, 'counter' => 99, 'sofa' => 3],
            'connections' => ['network' => 1, 'socket' => -4],
        ]);

        $this->assertSame(['desk' => 2, 'counter' => UnitConfiguration::MAX_FURNITURE], $configuration['furniture']);
        $this->assertSame(['network' => 1], $configuration['connections']);
        $this->assertArrayNotHasKey('furniture', UnitConfiguration::normalize(['size' => '6x3', 'furniture' => ['desk' => 0]]));
        $this->assertArrayNotHasKey('connections', UnitConfiguration::normalize(['size' => '6x3']));
    }

    public function test_only_chosen_items_can_be_uncertain(): void
    {
        $configuration = UnitConfiguration::normalize([
            'size' => '6x3',
            'interior' => ['toilet'],
            'furniture' => ['desk' => 1],
            'tentative' => ['toilet', 'kitchen', 'desk', 'chair'],
        ]);

        $this->assertSame(['toilet', 'desk'], $configuration['tentative']);
        $this->assertArrayNotHasKey('tentative', UnitConfiguration::normalize(['size' => '6x3', 'tentative' => ['toilet']]));
    }

    public function test_the_step_is_only_kept_when_chosen(): void
    {
        $this->assertTrue(UnitConfiguration::normalize(['size' => '3x3', 'steps' => '1'])['steps']);
        $this->assertArrayNotHasKey('steps', UnitConfiguration::normalize(['size' => '3x3', 'steps' => '0']));
    }

    public function test_a_hatch_takes_one_slot_and_is_not_a_window(): void
    {
        $configuration = UnitConfiguration::normalize(['size' => '3x3', 'walls' => ['front' => ['empty', 'hatch', 'empty']]]);

        $this->assertSame(['empty', 'hatch', 'empty'], $configuration['walls']['front']);
        [$hatch] = UnitConfiguration::openings($configuration['walls']['front'], 3);
        $this->assertSame('hatch', $hatch['type']);
        $this->assertEqualsWithDelta(1.1, $hatch['left'], 0.001);
        $this->assertEqualsWithDelta(1.9, $hatch['right'], 0.001);
    }

    public function test_heating_and_air_conditioning_can_be_combined_in_every_unit(): void
    {
        $configuration = UnitConfiguration::normalize(['size' => '6x3', 'modules' => 2, 'floors' => 2, 'interior' => ['airco', 'heater']]);

        foreach (['walls', 'upper_walls'] as $level) {
            $types = array_count_values(array_column(InteriorLayout::for($configuration, $level)['items'], 'type'));
            $this->assertSame(['airco' => 2, 'heater' => 2], $types);
        }
    }

    public function test_a_counter_stands_inside_the_hatch_with_a_chair_in_front_of_it(): void
    {
        $items = $this->items(UnitConfiguration::normalize(self::HERAS));
        $counter = $items['walls:counter:0'];
        $chair = $items['walls:chair:0'];

        $this->assertSame('front', $counter['wall']);
        $this->assertEqualsWithDelta(2.5, ($counter['x1'] + $counter['x2']) / 2, 0.001, 'The hatch in the third slot of the front wall is centred at 2.5 m.');
        $this->assertSame('front', $chair['wall']);
        $this->assertLessThan($counter['y1'], $chair['y1'], 'The chair stands on the room side of the counter.');
    }

    public function test_the_counter_gets_the_hatch_before_heating_is_placed(): void
    {
        $items = $this->items(UnitConfiguration::normalize([
            ...self::HERAS,
            'walls' => [...self::HERAS['walls'], 'front' => ['hatch', 'window', 'window', 'window', 'window', 'empty']],
        ]));

        $this->assertSame('front', $items['walls:counter:0']['wall']);
        $this->assertEqualsWithDelta(0.1, $items['walls:counter:0']['x1'], 0.001, 'The hatch is in the corner, so the counter stands against the left wall.');
        $this->assertNotSame('left', $items['walls:heater:0']['wall'], 'The heater keeps clear of the counter.');
    }

    public function test_a_desk_stands_inside_a_window_and_a_second_chair_goes_to_the_counter(): void
    {
        $items = $this->items(UnitConfiguration::normalize([
            ...self::HERAS,
            'furniture' => ['desk' => 1, 'counter' => 1, 'chair' => 2],
        ]));

        $this->assertSame('front', $items['walls:desk:0']['wall']);
        $this->assertEqualsWithDelta(1.02, ($items['walls:desk:0']['x1'] + $items['walls:desk:0']['x2']) / 2, 0.001, 'The desk takes the first window, clear of the corner post.');
        $this->assertEqualsWithDelta(1.02, ($items['walls:chair:0']['x1'] + $items['walls:chair:0']['x2']) / 2, 0.001);
        $this->assertEqualsWithDelta(2.5, ($items['walls:chair:1']['x1'] + $items['walls:chair:1']['x2']) / 2, 0.001);
    }

    public function test_sockets_stay_clear_of_doors_and_the_toilet(): void
    {
        $configuration = UnitConfiguration::normalize([...self::HERAS, 'connections' => ['socket' => 20]]);
        $items = $this->items($configuration);
        $toilet = $items['walls:toilet:0'];
        $sockets = array_filter($items, fn (array $item): bool => $item['type'] === 'socket');

        $this->assertCount(20, $sockets);
        foreach ($sockets as $socket) {
            $insideToilet = $socket['x1'] < $toilet['x2'] && $toilet['x1'] < $socket['x2'] && $socket['y1'] < $toilet['y2'] && $toilet['y1'] < $socket['y2'];
            $this->assertFalse($insideToilet, 'No socket inside the toilet cubicle.');
            $this->assertFalse($socket['wall'] === 'right' && $socket['y1'] < 2.0 && $socket['y2'] > 1.0, 'No socket where the door is.');
        }
    }

    public function test_dragged_items_keep_their_place_and_snap_to_a_wall_or_corner(): void
    {
        $configuration = UnitConfiguration::normalize([
            ...self::HERAS,
            'furniture' => ['desk' => 1],
            'positions' => [
                'walls:airco:0' => ['x' => 0.4, 'y' => 1.2],
                'walls:desk:0' => ['x' => 3.0, 'y' => 1.5],
                'walls:toilet:0' => ['x' => 5.5, 'y' => 2.8],
                'walls:kitchen:0' => ['x' => 1, 'y' => 1],
                'upper_walls:airco:0' => ['x' => 1, 'y' => 1],
            ],
        ]);

        $this->assertSame(['walls:toilet:0', 'walls:desk:0', 'walls:airco:0'], array_keys($configuration['positions']), 'Positions of items the unit does not have are dropped.');

        $items = $this->items($configuration);
        $this->assertSame('left', $items['walls:airco:0']['wall']);
        $this->assertEqualsWithDelta(1.2, ($items['walls:airco:0']['y1'] + $items['walls:airco:0']['y2']) / 2, 0.001);
        $this->assertSame(['front', 'right'], [$items['walls:toilet:0']['wall'], $items['walls:toilet:0']['side']]);
        $this->assertEqualsWithDelta(3.0, ($items['walls:desk:0']['x1'] + $items['walls:desk:0']['x2']) / 2, 0.001, 'A desk stands free in the room.');
        $this->assertEqualsWithDelta(1.5, ($items['walls:desk:0']['y1'] + $items['walls:desk:0']['y2']) / 2, 0.001);
    }

    public function test_dragged_positions_are_kept_within_the_unit(): void
    {
        $configuration = UnitConfiguration::normalize(['size' => '3x3', 'interior' => ['airco'], 'positions' => ['walls:airco:0' => ['x' => 9, 'y' => -2]]]);

        $this->assertSame(['x' => 3.0, 'y' => 0.0], $configuration['positions']['walls:airco:0']);
    }

    public function test_the_floor_plan_draws_the_new_items_and_lets_them_be_dragged(): void
    {
        $plan = (new FloorPlanRenderer)->render(self::HERAS);

        $this->assertStringContainsString('>Luik<', $plan);
        $this->assertStringContainsString('>800 / 700<', $plan);
        $this->assertStringContainsString('>Balie<', $plan);
        $this->assertStringContainsString('>UTP<', $plan);
        $this->assertStringContainsString('>optie<', $plan);
        $this->assertStringContainsString('>Verwarming (aan de wand, laag)<', $plan);
        $this->assertStringContainsString('>Wandcontactdoos<', $plan);
        $this->assertStringContainsString('>Indicatieve tekening<', $plan);
        $this->assertStringContainsString('data-item="walls:counter:0"', $plan);
        $this->assertStringContainsString('data-plan-level="walls"', $plan);
        $this->assertSame(1, substr_count($plan, 'fill="#eeeeee" fill-opacity="0.6"'), 'One step outside the one door.');
    }

    public function test_the_drawings_show_hatches_and_steps(): void
    {
        $drawing = app(UnitRenderer::class)->render(self::HERAS);
        $withoutStep = app(UnitRenderer::class)->render([...self::HERAS, 'steps' => '0']);

        $this->assertStringContainsString('#a7adb1', $drawing, 'The sill under the hatch.');
        $this->assertStringContainsString('#b3b1ab', $drawing, 'The top of the step.');
        $this->assertStringNotContainsString('#b3b1ab', $withoutStep);
    }

    public function test_the_composer_offers_the_new_options(): void
    {
        $this->get(route('unit-composer.public'))
            ->assertOk()
            ->assertSee('name="configuration[interior][]" value="heater"', false)
            ->assertSee('name="configuration[furniture][counter]"', false)
            ->assertSee('name="configuration[connections][network]"', false)
            ->assertSee('name="configuration[steps]"', false)
            ->assertSee('name="configuration[tentative][]" value="toilet"', false)
            ->assertSee('Uitgifteluik (80 cm, wind- en waterdicht)')
            ->assertSee('data-configurator-positions-reset', false);
    }

    public function test_a_shared_composition_restores_dragged_positions(): void
    {
        $configuration = UnitConfiguration::normalize([...self::HERAS, 'positions' => ['walls:airco:0' => ['x' => 0.2, 'y' => 1.5]]]);

        $this->get(route('unit-composer.public', [UnitConfiguration::SHARE_KEY => UnitConfiguration::toShareCode($configuration)]))
            ->assertOk()
            ->assertSee('name="configuration[positions][walls:airco:0][x]" value="0.2"', false)
            ->assertSee('name="configuration[furniture][counter]" value="1"', false);
    }

    public function test_the_heras_request_can_be_composed_and_sent_as_a_quote(): void
    {
        Mail::fake();

        $this->post(route('unit-composer.public.preview'), ['configuration' => self::HERAS])
            ->assertOk()
            ->assertSee('>Balie<', false);

        $this->postJson(route('unit-composer.quote'), ['configuration' => self::HERAS])
            ->assertOk()
            ->assertJson(['count' => 1]);

        $this->post(route('quote.submit'), [
            'first_name' => 'Rob',
            'last_name' => 'Martens',
            'email' => 'rob@example.com',
            'message' => 'Huur circa 18 maanden in Beverwijk, plaatsing eind Q1 / begin Q2 2027.',
        ])->assertRedirect(route('quote.show'));

        $item = Submission::query()->sole()->items[0];
        $this->assertSame(ComposedUnit::TYPE, $item['type']);
        $this->assertStringContainsString('Modulaire unit van 6 x 3 meter', $item['title']);
        $this->assertStringContainsString('Wanden antraciet, frame antraciet, 4 ramen, 1 deur, 1 uitgifteluik, opstapje', $item['title']);
        $this->assertStringContainsString('inrichting: airco, verwarming, sanitair (optioneel)', $item['title']);
        $this->assertStringContainsString('meubilair: 1× bureaustoel, 1× uitgiftebalie', $item['title']);
        $this->assertStringContainsString('elektra: 4× wandcontactdoos, 1× netwerkaansluiting (utp)', $item['title']);
        $this->assertStringContainsString('gebruik: portiersloge', $item['title']);

        $code = (string) parse_url($item['url'], PHP_URL_QUERY);
        parse_str($code, $query);
        $shared = UnitConfiguration::fromShareCode($query[UnitConfiguration::SHARE_KEY]);
        $this->assertSame(UnitConfiguration::normalize(self::HERAS), $shared, 'The link in the quote opens exactly this composition.');
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, array<string, mixed>>
     */
    private function items(array $configuration): array
    {
        return array_column(InteriorLayout::for($configuration, 'walls')['items'], null, 'id');
    }
}
