<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Services\UnitDesigner;
use App\Support\UnitConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
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
            ->assertSee('data-preview-url="'.route('admin.units.preview').'"', false)
            ->assertSee('Kopieer link')
            ->assertSee('Bewerken met AI')
            ->assertDontSee('Genereer met AI')
            ->assertDontSee('Geen configuratie')
            ->assertDontSee('name="photos_3d_enabled"', false);

        $this->actingAs(User::factory()->create())->get(route('admin.unit-composer'))->assertForbidden();
    }

    public function test_customers_can_compose_a_unit_in_their_account(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('dashboard'))->assertOk()->assertSee(route('unit-composer'), false);

        $this->actingAs($customer)->get(route('unit-composer'))
            ->assertOk()
            ->assertSee('Unit samenstellen')
            ->assertDontSee('Unit samenstellen (test)')
            ->assertSee('data-preview-url="'.route('unit-composer.preview').'"', false)
            ->assertSee('data-design-url="'.route('unit-composer.design').'"', false)
            ->assertSee('Bewerken met AI');

        $this->actingAs($customer)->post(route('unit-composer.preview'), ['configuration' => ['size' => '3x3']])
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_customers_can_edit_their_composition_with_ai(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $design = ['configuration' => UnitConfiguration::normalize(UnitConfiguration::STARTER), 'title' => 'Unit', 'meta_description' => 'Meta'];
        $this->mock(UnitDesigner::class, function (MockInterface $mock) use ($design): void {
            $mock->shouldReceive('design')->once()->andReturn($design);
        });

        $this->actingAs(User::factory()->create())
            ->postJson(route('unit-composer.design'), ['description' => 'Maak de wanden blauw', 'configuration' => UnitConfiguration::STARTER])
            ->assertOk()
            ->assertJson($design);
    }

    public function test_anyone_can_compose_a_unit_on_the_public_page_without_ai(): void
    {
        $this->mock(UnitDesigner::class, fn (MockInterface $mock) => $mock->shouldNotReceive('design'));

        $this->get('/unit-samensteller/')
            ->assertOk()
            ->assertSee('Unit samenstellen')
            ->assertSee('data-composer', false)
            ->assertSee('data-preview-url="'.route('unit-composer.public.preview').'"', false)
            ->assertSee('Kopieer link')
            ->assertDontSee('data-design-url', false)
            ->assertDontSee('Bewerken met AI')
            ->assertDontSee('data-configurator-generate', false)
            ->assertSee('aria-label="Legenda"', false)
            ->assertSee('Raam (180 cm)')
            ->assertSee('Deur (87,5 cm)');

        $this->assertMatchesRegularExpression('/data-configurator-quote data-quote-url="[^"]+"\s+hidden\s*>/', $this->get('/unit-samensteller/')->getContent());

        $this->post(route('unit-composer.public.preview'), ['configuration' => ['size' => '3x3']])
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->postJson('/unit-samensteller/ontwerpen', ['description' => 'Maak de wanden blauw'])->assertStatus(405);
        $this->postJson(route('unit-composer.design'), ['description' => 'Maak de wanden blauw'])->assertUnauthorized();
    }

    public function test_the_public_page_reads_a_shared_composition(): void
    {
        $encoded = strtr(base64_encode((string) json_encode(['size' => '3x3', 'floors' => 2])), '+/', '-_');

        $this->get('/unit-samensteller/?'.UnitConfiguration::SHARE_KEY.'='.$encoded)
            ->assertOk()
            ->assertSee('<option value="2" selected>2 lagen (gestapeld)</option>', false)
            ->assertSee('data-configurator-quote data-quote-url="'.route('unit-composer.quote').'"', false)
            ->assertDontSee('data-configurator-quote data-quote-url="'.route('unit-composer.quote').'"  hidden', false);
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('unit-composer'))->assertRedirect(route('login'));
        $this->post(route('unit-composer.preview'), ['configuration' => ['size' => '3x3']])->assertRedirect(route('login'));
    }

    public function test_a_composition_is_read_from_the_link(): void
    {
        $composition = ['size' => '3x3', 'modules' => 3, 'background' => 'site', 'walls' => ['right' => ['door']]];
        $encoded = strtr(base64_encode((string) json_encode($composition)), '+/', '-_');

        $this->actingAs(User::factory()->create())
            ->get(route('unit-composer', [UnitConfiguration::SHARE_KEY => $encoded]))
            ->assertOk()
            ->assertSee('<option value="3x3" selected>', false)
            ->assertSee('<option value="3" selected>3 units gekoppeld</option>', false)
            ->assertSee('<option value="site" selected>', false)
            ->assertSee('3 gekoppelde units van 3 x 3 meter (3 x 9 meter)');
    }

    public function test_customers_start_from_an_anthracite_6x3_with_two_windows_and_a_door_on_the_right(): void
    {
        $this->assertSame(UnitConfiguration::normalize(UnitConfiguration::STARTER), UnitConfiguration::fromShareCode('geen-geldige-samenstelling'));

        $response = $this->actingAs(User::factory()->create())
            ->get(route('unit-composer'))
            ->assertOk()
            ->assertSee('<option value="6x3" selected>', false)
            ->assertSee('name="configuration[colors][cladding]" value="#383e42" checked', false)
            ->assertSee('name="configuration[colors][frame]" value="#383e42" checked', false);

        $this->assertSame(1, substr_count($response->getContent(), 'name="configuration[walls][right][]" value="door"'));
        $this->assertSame(4, substr_count($response->getContent(), 'name="configuration[walls][front][]" value="window"'), 'Two windows of two slots each.');
    }

    public function test_the_home_page_shows_the_public_composer_below_the_hero(): void
    {
        $this->mock(UnitDesigner::class, fn (MockInterface $mock) => $mock->shouldNotReceive('design'));
        Page::factory()->create(['slug' => '', 'template' => 'landing', 'hero' => ['title' => 'Modulaire Units'], 'content' => '<p>Welkom bij de modulaire units</p>']);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['mh-home-hero__title', 'Stel je eigen unit samen', 'data-composer', 'Welkom bij de modulaire units'], false)
            ->assertSee('data-preview-url="'.route('unit-composer.public.preview').'"', false)
            ->assertSee('Type gebruik')
            ->assertDontSee('Bewerken met AI');

        Page::factory()->create(['slug' => 'contact']);
        $this->get('/contact/')->assertOk()->assertDontSee('data-composer', false);
    }

    public function test_visitors_choose_what_the_unit_will_be_used_for(): void
    {
        $encoded = UnitConfiguration::toShareCode(UnitConfiguration::normalize(['size' => '3x3', 'usage' => ['kantine', 'kantoor', 'onbekend']]));

        $this->assertSame(['kantoor', 'kantine'], UnitConfiguration::fromShareCode($encoded)['usage']);
        $this->assertArrayNotHasKey('usage', UnitConfiguration::normalize(['size' => '3x3']));

        $this->get('/unit-samensteller/?'.UnitConfiguration::SHARE_KEY.'='.$encoded)
            ->assertOk()
            ->assertSee('name="configuration[usage][]" multiple', false)
            ->assertSee('<option value="kantoor" selected>Kantoor</option>', false)
            ->assertSee('Conciërge</option>', false);

        $this->postJson(route('unit-composer.quote'), ['configuration' => ['size' => '3x3', 'usage' => ['onbekend']]])->assertUnprocessable();
        $this->postJson(route('unit-composer.quote'), ['configuration' => ['size' => '3x3', 'usage' => ['kantoor', 'kantine']]])->assertOk();

        $this->get('/mijn-offerte/')->assertSee('gebruik: kantoor, kantine');
    }
}
