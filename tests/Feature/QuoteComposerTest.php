<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UnitConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_compose_a_quote_with_the_options_customers_do_not_get(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee(route('quote-composer'), false);

        $this->actingAs($admin)->get(route('quote-composer'))
            ->assertOk()
            ->assertSee('Offerte samenstellen')
            ->assertSee('data-preview-url="'.route('unit-composer.preview').'"', false)
            ->assertSee('data-configurator-quote data-quote-url="'.route('unit-composer.quote').'">', false)
            ->assertSee('data-configurator-share-copy', false)
            ->assertSee('name="configuration[usage][]"', false)
            ->assertSee('name="configuration[connections][socket]"', false)
            ->assertSee('name="configuration[connections][network]"', false)
            ->assertSee('name="configuration[furniture][desk]"', false)
            ->assertSee('name="configuration[furniture][cabinet]"', false)
            ->assertSee('name="configuration[steps]"', false)
            ->assertSee('data-tentative-for="desk"', false)
            ->assertDontSee('Unit toevoegen aan producten')
            ->assertDontSee('data-design-url', false);
    }

    public function test_the_customer_composer_still_leaves_these_options_out(): void
    {
        $this->actingAs(User::factory()->create())->get(route('unit-composer'))
            ->assertOk()
            ->assertDontSee('name="configuration[usage][]"', false)
            ->assertDontSee('name="configuration[connections][socket]"', false)
            ->assertDontSee('name="configuration[furniture][desk]"', false)
            ->assertDontSee('name="configuration[steps]"', false)
            ->assertDontSee('data-tentative-for="desk"', false);
    }

    public function test_only_admins_can_open_the_quote_composer(): void
    {
        $this->get(route('quote-composer'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('quote-composer'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertDontSee(route('quote-composer'), false);
    }

    public function test_a_shared_composition_opens_with_its_extras_filled_in(): void
    {
        $encoded = UnitConfiguration::toShareCode(UnitConfiguration::normalize([
            'size' => '6x3',
            'usage' => ['portiersloge'],
            'furniture' => ['desk' => 2],
            'connections' => ['socket' => 6],
            'steps' => true,
        ]));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('quote-composer', [UnitConfiguration::SHARE_KEY => $encoded]))
            ->assertOk()
            ->assertSee('<option value="portiersloge" selected>', false)
            ->assertSee('name="configuration[furniture][desk]" value="2"', false)
            ->assertSee('name="configuration[connections][socket]" value="6"', false)
            ->assertSee('data-configurator-steps checked', false);
    }

    public function test_the_extras_are_listed_with_the_unit_in_the_quote(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson(route('unit-composer.quote'), ['configuration' => [
            'size' => '6x3',
            'usage' => ['kantoor'],
            'furniture' => ['desk' => 2, 'chair' => 2],
            'connections' => ['socket' => 6, 'network' => 2],
            'tentative' => ['chair'],
            'steps' => true,
        ]])->assertOk();

        $this->get('/mijn-offerte/')
            ->assertOk()
            ->assertSee('opstapje')
            ->assertSee('meubilair: 2× bureau, 2× bureaustoel (optioneel)')
            ->assertSee('elektra: 6× wandcontactdoos, 2× netwerkaansluiting (utp)')
            ->assertSee('gebruik: kantoor');
    }
}
