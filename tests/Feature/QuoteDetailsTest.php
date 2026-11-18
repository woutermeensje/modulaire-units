<?php

namespace Tests\Feature;

use App\Models\Quote;
use App\Models\User;
use App\Services\QuotePdf;
use App\Support\Locale;
use App\Support\QuotePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Everything a quote has besides the composed unit: sale or rent, project details, the technical specification, price
 * lines, terms, open points and internal notes. See units.md.
 */
class QuoteDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_quotes_have_their_own_overview_for_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $rental = Quote::factory()->create([
            'title' => 'Offerte pasuitgiftepost',
            'kind' => 'rent',
            'rental_weeks' => 78,
            'lines' => [['description' => 'Huur unit', 'quantity' => 1, 'price' => 6000, 'period' => 'week', 'status' => 'price']],
        ]);
        $sale = Quote::factory()->create(['title' => 'Offerte kantoorunit', 'kind' => 'sale']);

        $this->actingAs($admin)->get(route('quote-composer'))
            ->assertSee(route('quotes.index'), false)
            ->assertSee('Verstuurde offertes bekijken (2)')
            ->assertDontSee('Offerte pasuitgiftepost');

        $this->actingAs($admin)->get(route('quotes.index'))
            ->assertOk()
            ->assertSee('Offerte pasuitgiftepost')
            ->assertSee('Offerte kantoorunit')
            ->assertSee('78 weken')
            ->assertSee('€ 4.680,00')
            ->assertSee('waarvan € 60,00 per week')
            ->assertSee(route('quotes.pdf', $rental), false)
            ->assertSee(route('quote-composer', ['kopie' => $sale->id]), false);

        $this->actingAs($admin)->get(route('quotes.index', ['soort' => 'rent']))
            ->assertSee('Offerte pasuitgiftepost')
            ->assertDontSee('Offerte kantoorunit');

        $this->actingAs(User::factory()->create())->get(route('quotes.index'))->assertForbidden();
    }

    public function test_a_rental_quote_keeps_its_details_and_shows_them_in_the_pdf(): void
    {
        Mail::fake();
        Storage::fake('local');
        $client = User::factory()->create(['company_name' => 'Hekwerk B.V.']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('quote-composer.send'), $this->rentalInput($client))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('quotes.index'));

        $quote = Quote::sole();
        $this->assertSame('rent', $quote->kind);
        $this->assertSame(78, $quote->rental_weeks);
        $this->assertSame('Eind Q1 / begin Q2 2027', $quote->delivery_date);
        $this->assertSame(6000, $quote->lines[0]['price'], 'Prices are kept in cents.');
        $this->assertSame('week', $quote->lines[0]['period']);
        $this->assertCount(6, $quote->lines, 'The empty row of the form is left out.');
        $this->assertArrayNotHasKey('roof', $quote->specifications, 'Empty sections are not kept.');

        $html = app(QuotePdf::class)->html($quote);

        // Project details.
        $this->assertStringContainsString('78 weken', $html);
        $this->assertStringContainsString('Eind Q1 / begin Q2 2027', $html);
        // The exact size of the supplier, and the admin's specification lines in their sections.
        $this->assertStringContainsString('Buitenmaat per unit 6.058 x 2.990 x 2.860 mm (l x b x h)', $html);
        $this->assertStringContainsString('Binnenhoogte 2.500 mm', $html);
        $this->assertStringContainsString('100 mm minerale wol A1 in dak, wanden en vloer', $html);
        $this->assertStringContainsString('Split-airco 2,0 kW koelen', $html);
        $this->assertStringContainsString('Fundering en locatie', $html);
        // Prices: €60 a week for 78 weeks plus €350 transport; the option and open costs are not in the total.
        $this->assertStringContainsString('Huur over 78 weken', $html);
        $this->assertStringContainsString('€ 4.680,00', $html);
        $this->assertStringContainsString('Totaal excl. btw', $html);
        $this->assertStringContainsString('€ 5.030,00', $html);
        $this->assertStringContainsString('€ 1.056,30', $html);
        $this->assertStringContainsString('€ 6.086,30', $html);
        $this->assertStringContainsString('Opties (niet in het totaal)', $html);
        $this->assertStringContainsString('Netwerkkabel naar de unit', $html);
        $this->assertStringContainsString('Nog te bepalen kosten', $html);
        $this->assertStringContainsString('Retourtransport', $html);
        $this->assertStringContainsString('Niet inbegrepen, door de opdrachtgever te regelen', $html);
        $this->assertStringContainsString('Stelconplaten', $html);
        // Open points and terms.
        $this->assertStringContainsString('Is een toilet nodig?', $html);
        $this->assertStringContainsString('Minimale huurtermijn 26 weken', $html);
        // Never for the client.
        $this->assertStringNotContainsString('Inkoop', $html);
    }

    public function test_the_prices_add_up(): void
    {
        $price = new QuotePrice([
            ['description' => 'Huur', 'quantity' => 2, 'price' => 6000, 'period' => 'week', 'status' => 'price'],
            ['description' => 'Transport', 'quantity' => 1, 'price' => 35000, 'period' => 'once', 'status' => 'price'],
            ['description' => 'Verwarming', 'quantity' => 1, 'price' => null, 'period' => 'once', 'status' => 'included'],
            ['description' => 'Airco', 'quantity' => 1, 'price' => 149300, 'period' => 'once', 'status' => 'optional'],
            ['description' => 'Kraan', 'quantity' => 1, 'price' => 50000, 'period' => 'once', 'status' => 'tbd'],
        ], 10);

        $this->assertSame(12000, $price->weekly());
        $this->assertSame(120000, $price->rental());
        $this->assertSame(35000, $price->oneOff());
        $this->assertSame(155000, $price->totalExcludingVat(), 'Options and costs still to be determined are left out.');
        $this->assertSame(32550, $price->vat());
        $this->assertSame(187550, $price->totalIncludingVat());

        $this->assertNull((new QuotePrice([], null))->rental(), 'Without a rental period there is no total rent.');
    }

    public function test_a_sale_is_charged_once_without_a_rental_period(): void
    {
        Mail::fake();
        Storage::fake('local');
        $input = $this->rentalInput(User::factory()->create());
        $input['kind'] = 'sale';

        $this->actingAs(User::factory()->admin()->create())->post(route('quote-composer.send'), $input)->assertSessionHasNoErrors();

        $quote = Quote::sole();
        $this->assertNull($quote->rental_weeks);
        $this->assertSame(['once'], array_values(array_unique(array_column($quote->lines, 'period'))));
        $this->assertStringNotContainsString('Huurperiode', app(QuotePdf::class)->html($quote));
    }

    public function test_a_charged_or_optional_line_needs_a_price(): void
    {
        Mail::fake();
        $input = $this->rentalInput(User::factory()->create());
        $input['lines'] = [['description' => 'Transport', 'quantity' => 1, 'price' => '', 'period' => 'once', 'status' => 'price']];

        $this->actingAs(User::factory()->admin()->create())->post(route('quote-composer.send'), $input)
            ->assertSessionHasErrors('lines.0.price');

        $input['lines'][0]['status'] = 'tbd';
        $this->post(route('quote-composer.pdf-preview'), $input)->assertOk();
        $this->assertSame(0, Quote::count());
    }

    public function test_an_earlier_quote_can_be_copied_into_a_new_one(): void
    {
        $quote = Quote::factory()->create([
            'kind' => 'rent',
            'rental_weeks' => 78,
            'reference' => 'Pasuitgiftepost eindklant',
            'valid_until' => '2026-01-01',
            'specifications' => ['insulation' => '100 mm minerale wol A1'],
            'lines' => [['description' => 'Huur unit', 'quantity' => 1, 'price' => 6000, 'period' => 'week', 'status' => 'price']],
            'internal_notes' => 'Inkoop via leverancier',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('quote-composer', ['kopie' => $quote->id]))
            ->assertOk()
            ->assertSee('Ingevuld met de gegevens van '.$quote->number())
            ->assertSee('value="'.$quote->title.'"', false)
            ->assertSee('value="Pasuitgiftepost eindklant"', false)
            ->assertSee('<option value="rent" selected', false)
            ->assertSee('100 mm minerale wol A1')
            ->assertSee('value="Huur unit"', false)
            ->assertSee('value="60.00"', false)
            ->assertSee('Inkoop via leverancier')
            ->assertDontSee('value="2026-01-01"', false)
            ->assertSee('value="'.now()->addMonth()->format('Y-m-d').'"', false);
    }

    public function test_a_new_quote_starts_with_the_costs_every_quote_has_to_mention(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('quote-composer'))
            ->assertOk()
            ->assertSee('name="kind"', false)
            ->assertSee('name="rental_weeks"', false)
            ->assertSee('name="specifications[insulation]"', false)
            ->assertSee('name="terms"', false)
            ->assertSee('name="open_points"', false)
            ->assertSee('name="internal_notes"', false)
            ->assertSee('value="Transport naar de locatie"', false)
            ->assertSee('data-quote-line-template', false);
    }

    public function test_the_quote_tool_shows_the_exact_size_and_the_customer_composer_the_rounded_one(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('quote-composer'))
            ->assertSee('6.058 x 2.990 mm (6 x 3 meter)')
            ->assertSee('2.990 x 2.990 mm (3 x 3 meter)');

        $this->actingAs(User::factory()->create())->get(route('unit-composer'))
            ->assertSee('6 x 3 meter')
            ->assertDontSee('6.058 x 2.990 mm');
    }

    public function test_an_english_quote_has_english_prices(): void
    {
        $quote = Quote::factory()->create([
            'locale' => 'en',
            'kind' => 'rent',
            'rental_weeks' => 78,
            'lines' => [['description' => 'Unit rental', 'quantity' => 1, 'price' => 6000, 'period' => 'week', 'status' => 'price']],
        ]);

        $html = Locale::using('en', fn (): string => app(QuotePdf::class)->html($quote));

        $this->assertStringContainsString('Rent over 78 weeks', $html);
        $this->assertStringContainsString('€ 4,680.00', $html);
        $this->assertStringContainsString('VAT 21%', $html);
        $this->assertStringNotContainsString('btw', $html);
    }

    /**
     * A rental like the ones that come in: one 6x3 unit as a pass issue point for 78 weeks.
     *
     * @return array<string, mixed>
     */
    private function rentalInput(User $client): array
    {
        return [
            'title' => 'Offerte pasuitgiftepost',
            'locale' => 'nl',
            'client_id' => $client->id,
            'kind' => 'rent',
            'rental_weeks' => '78',
            'reference' => 'Pasuitgiftepost eindklant',
            'delivery_address' => 'Nog niet bekend',
            'delivery_date' => 'Eind Q1 / begin Q2 2027',
            'valid_until' => now()->addMonth()->format('Y-m-d'),
            'intro' => 'Beste Rob,',
            'configuration' => [
                'size' => '6x3',
                'interior' => ['airco', 'toilet'],
                'tentative' => ['toilet'],
                'furniture' => ['counter' => 1, 'chair' => 1],
                'connections' => ['socket' => 4, 'network' => 1],
                'steps' => '1',
                'walls' => ['front' => ['empty', 'window', 'window', 'empty', 'empty', 'empty'], 'right' => ['empty', 'hatch', 'empty'], 'left' => ['empty', 'door', 'empty']],
            ],
            'specifications' => [
                'insulation' => "- 100 mm minerale wol A1 in dak, wanden en vloer\n\n",
                'climate' => 'Split-airco 2,0 kW koelen',
                'site' => 'Vlakke ondergrond van stelconplaten',
                'roof' => '   ',
            ],
            'lines' => [
                ['description' => 'Huur unit', 'quantity' => '1', 'price' => '60', 'period' => 'week', 'status' => 'price'],
                ['description' => 'Transport en plaatsing', 'quantity' => '1', 'price' => '350.00', 'period' => 'once', 'status' => 'price'],
                ['description' => 'Verwarming', 'quantity' => '1', 'price' => '', 'period' => 'once', 'status' => 'included'],
                ['description' => 'Netwerkkabel naar de unit', 'quantity' => '1', 'price' => '125', 'period' => 'once', 'status' => 'optional'],
                ['description' => 'Retourtransport', 'quantity' => '1', 'price' => '', 'period' => 'once', 'status' => 'tbd'],
                ['description' => 'Stelconplaten', 'quantity' => '1', 'price' => '', 'period' => 'once', 'status' => 'client'],
                ['description' => '', 'quantity' => '1', 'price' => '', 'period' => 'once', 'status' => 'price'],
            ],
            'terms' => 'Minimale huurtermijn 26 weken',
            'open_points' => "Is een toilet nodig?\nWelke afmeting krijgt het uitgifteluik?",
            'internal_notes' => 'Inkoop € 4.000 gebruikt',
        ];
    }
}
