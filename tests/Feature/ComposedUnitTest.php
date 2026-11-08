<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Support\UnitConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComposedUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_get_a_title_description_and_add_to_products_button_instead_of_quote_and_share_link(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('unit-composer'))
            ->assertOk()
            ->assertSee('action="'.route('unit-composer.store').'"', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="price_rent_week"', false)
            ->assertDontSee('name="price_buy_min"', false)
            ->assertDontSee('name="price_buy_max"', false)
            ->assertSee('Unit toevoegen aan producten')
            ->assertSee('name="photos_3d_enabled"', false)
            ->assertDontSee('data-configurator-quote', false)
            ->assertDontSee('data-configurator-share-copy', false)
            ->assertDontSee('Bewerken met AI');
    }

    public function test_admins_add_a_composed_unit_to_the_products_as_draft(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        Unit::factory()->create(['title' => 'Portiersloge', 'slug' => 'portiersloge']);

        $this->actingAs($admin)
            ->post(route('unit-composer.store'), [
                'title' => 'Portiersloge',
                'description' => '<p>Een <strong>compacte</strong> portiersloge.</p>',
                'price_rent_week' => '€ 75,-',
                'configuration' => UnitConfiguration::STARTER,
            ])
            ->assertRedirect(route('my-used-listings.index'))
            ->assertSessionHasNoErrors();

        $unit = Unit::query()->where('user_id', $admin->id)->sole();
        $this->assertSame('portiersloge-2', $unit->slug);
        $this->assertSame('draft', $unit->status);
        $this->assertSame('<p>Een <strong>compacte</strong> portiersloge.</p>', $unit->content);
        $this->assertSame(UnitConfiguration::normalize(UnitConfiguration::STARTER), $unit->configuration);
        $this->assertSame('€ 75,- per week', $unit->rentPrice());
        $this->assertSame('Op aanvraag', $unit->buyPrice());
        $this->assertNotEmpty($unit->configuration_images);
        $this->assertNotNull($unit->featured_image);

        $this->actingAs($admin)
            ->get(route('my-used-listings.index'))
            ->assertOk()
            ->assertSee('Portiersloge')
            ->assertSee('action="'.route('composed-units.status', $unit).'"', false)
            ->assertSee('<option value="draft" selected>Concept</option>', false)
            ->assertSee(route('admin.units.edit', $unit->id), false)
            ->assertDontSee('Units aanbieden');
    }

    public function test_a_title_and_size_are_required(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->from(route('unit-composer'))
            ->post(route('unit-composer.store'), ['configuration' => ['modules' => 1]])
            ->assertRedirect(route('unit-composer'))
            ->assertSessionHasErrors(['title', 'configuration.size']);

        $this->assertDatabaseCount('units', 0);
    }

    public function test_customers_cannot_add_units_to_the_products(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->post(route('unit-composer.store'), ['title' => 'Kantoorunit', 'configuration' => UnitConfiguration::STARTER])
            ->assertForbidden();

        $this->assertDatabaseCount('units', 0);

        $this->actingAs($customer)
            ->get(route('my-used-listings.index'))
            ->assertOk()
            ->assertSee('Units aanbieden')
            ->assertDontSee('data-submit-on-change', false);
    }

    public function test_admins_publish_and_unpublish_a_unit_from_the_list(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = Unit::factory()->create(['user_id' => $admin->id, 'status' => 'draft', 'published_at' => null]);

        $this->actingAs($admin)
            ->patch(route('composed-units.status', $unit), ['status' => 'published'])
            ->assertRedirect(route('my-used-listings.index'));

        $unit->refresh();
        $this->assertTrue($unit->isPublished());
        $this->assertNotNull($unit->published_at);

        $this->actingAs($admin)->patch(route('composed-units.status', $unit), ['status' => 'offline']);
        $this->assertSame('offline', $unit->refresh()->status);
        $this->assertFalse($unit->isPublished());

        $this->actingAs($admin)->patch(route('composed-units.status', $unit), ['status' => 'verwijderd'])->assertSessionHasErrors('status');
        $this->assertSame('offline', $unit->refresh()->status);
    }

    public function test_customers_cannot_change_the_status_of_a_unit(): void
    {
        $unit = Unit::factory()->create(['status' => 'draft']);

        $this->actingAs(User::factory()->create())
            ->patch(route('composed-units.status', $unit), ['status' => 'published'])
            ->assertForbidden();

        $this->assertSame('draft', $unit->refresh()->status);
    }

    public function test_the_week_price_goes_before_the_imported_rent_price_and_the_purchase_price_is_on_request(): void
    {
        $unit = Unit::factory()->make(['price_rent' => 'Vanaf € 250,- per maand', 'price_buy' => 'Vanaf € 4.950,-']);
        $this->assertSame('Vanaf € 250,- per maand', $unit->rentPrice());
        $this->assertSame('Op aanvraag', $unit->buyPrice());

        $unit->fill(['price_rent_week' => '€ 75,-']);
        $this->assertSame('€ 75,- per week', $unit->rentPrice());

        $this->assertNull(Unit::factory()->make()->rentPrice());
    }

    public function test_amounts_without_a_euro_sign_get_one(): void
    {
        $this->assertSame('€ 50 per week', Unit::factory()->make(['price_rent_week' => '50'])->rentPrice());
        $this->assertSame('€ 4.500', Unit::euro('4500'));
        $this->assertSame('€ 7.500,-', Unit::euro('7.500,-'));
        $this->assertSame('Prijs op aanvraag', Unit::euro('Prijs op aanvraag'));
    }
}
