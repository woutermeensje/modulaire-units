<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\UnitComposerController;
use App\Models\User;
use App\Support\UnitConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_open_the_composer_test_environment(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.unit-composer'))
            ->assertOk()
            ->assertSee('Unit samenstellen (test)')
            ->assertSee('data-composer', false)
            ->assertSee('Kopieer link')
            ->assertSee('Bewerken met AI')
            ->assertDontSee('Genereer met AI')
            ->assertDontSee('Geen configuratie')
            ->assertDontSee('name="photos_3d_enabled"', false);

        $this->actingAs(User::factory()->create())->get(route('admin.unit-composer'))->assertForbidden();
    }

    public function test_a_composition_is_read_from_the_link(): void
    {
        $composition = ['size' => '3x3', 'modules' => 3, 'background' => 'site', 'walls' => ['right' => ['door']]];
        $encoded = strtr(base64_encode((string) json_encode($composition)), '+/', '-_');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.unit-composer', [UnitComposerController::QUERY_KEY => $encoded]))
            ->assertOk()
            ->assertSee('<option value="3x3" selected>', false)
            ->assertSee('<option value="3" selected>3 units gekoppeld</option>', false)
            ->assertSee('<option value="site" selected>', false)
            ->assertSee('3 gekoppelde units van 3 x 3 meter (3 x 9 meter)');
    }

    public function test_customers_start_from_an_anthracite_6x3_with_two_windows_and_a_door_on_the_right(): void
    {
        $this->assertSame(['empty', 'window', 'empty', 'empty', 'window', 'empty'], UnitConfiguration::normalize(UnitComposerController::STARTER_UNIT)['walls']['front']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.unit-composer', [UnitComposerController::QUERY_KEY => 'geen-geldige-samenstelling']))
            ->assertOk()
            ->assertSee('<option value="6x3" selected>', false)
            ->assertSee('name="configuration[colors][cladding]" value="#383e42" checked', false)
            ->assertSee('name="configuration[colors][frame]" value="#383e42" checked', false);

        $this->assertSame(1, substr_count($response->getContent(), 'name="configuration[walls][right][]" value="door"'));
        $this->assertSame(2, substr_count($response->getContent(), 'name="configuration[walls][front][]" value="window"'));
    }
}
