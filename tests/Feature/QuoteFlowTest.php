<?php

namespace Tests\Feature;

use App\Mail\SubmissionReceived;
use App\Models\Submission;
use App\Models\Unit;
use App\Support\ComposedUnit;
use App\Support\UnitConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuoteFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_compose_and_submit_a_quote(): void
    {
        Mail::fake();
        $unit = Unit::factory()->create(['title' => 'Kantoorunit 20ft']);

        $this->post(route('quote.add'), ['type' => 'unit', 'id' => $unit->id, 'quantity' => 2])->assertRedirect();

        $this->get('/mijn-offerte/')->assertOk()->assertSee('Kantoorunit 20ft')->assertSee('value="2"', false);

        $this->post(route('quote.submit'), [
            'first_name' => 'Jan',
            'last_name' => 'Jansen',
            'email' => 'jan@example.com',
            'message' => 'Graag levering in Delft',
        ])->assertRedirect(route('quote.show'));

        $submission = Submission::query()->sole();
        $this->assertSame('offerte', $submission->type);
        $this->assertSame(2, $submission->items[0]['quantity']);
        Mail::assertSent(SubmissionReceived::class);

        $this->get('/mijn-offerte/')->assertSee('Bedankt voor je aanvraag!');
        $this->get('/mijn-offerte/')->assertSee('Je offerte is leeg');
    }

    public function test_header_call_to_action_leads_to_the_quote_once_it_has_items(): void
    {
        $unit = Unit::factory()->create();

        $this->get('/mijn-offerte/')
            ->assertSee('data-quote-cta>Informatie aanvragen</a>', false)
            ->assertDontSee('data-quote-cta>Mijn offerte</a>', false);

        $this->post(route('quote.add'), ['type' => 'unit', 'id' => $unit->id]);

        $this->get('/mijn-offerte/')
            ->assertSee('href="'.route('quote.show').'/" class="mh-btn mh-btn--solid mh-btn--nav-contact" data-quote-cta>Mijn offerte</a>', false)
            ->assertDontSee('data-quote-cta>Informatie aanvragen</a>', false)
            ->assertDontSee('mh-btn--nav-quote', false);
    }

    public function test_quantities_can_be_updated_and_items_removed(): void
    {
        $unit = Unit::factory()->create();
        $this->post(route('quote.add'), ['type' => 'unit', 'id' => $unit->id]);

        $this->post(route('quote.update'), ['quantities' => ["unit:{$unit->id}" => 5]]);
        $this->get('/mijn-offerte/')->assertSee('value="5"', false);

        $this->post(route('quote.update'), ['remove' => "unit:{$unit->id}"]);
        $this->get('/mijn-offerte/')->assertSee('Je offerte is leeg');
    }

    public function test_draft_units_cannot_be_added(): void
    {
        $unit = Unit::factory()->create(['status' => 'draft']);

        $this->post(route('quote.add'), ['type' => 'unit', 'id' => $unit->id])->assertNotFound();
    }

    public function test_a_composition_from_the_unit_composer_can_be_quoted(): void
    {
        Mail::fake();
        $composition = ['size' => '6x3', 'modules' => 2, 'walls' => ['front' => ['empty', 'window', 'window', 'empty', 'door', 'empty']]];

        $this->postJson(route('unit-composer.quote'), ['configuration' => $composition])
            ->assertOk()
            ->assertJson(['count' => 1]);
        $this->postJson(route('unit-composer.quote'), ['configuration' => $composition])->assertJson(['count' => 1]);

        $configuration = UnitConfiguration::normalize($composition);
        $share = UnitConfiguration::toShareCode($configuration);

        $this->get('/mijn-offerte/')
            ->assertOk()
            ->assertSee('Eigen samenstelling: 2 gekoppelde units van 6 x 3 meter (6 x 6 meter)')
            ->assertSee('1 raam, 1 deur')
            ->assertSee('value="2"', false)
            ->assertSee('samenstelling='.$share, false)
            ->assertSee('class="mh-qp__thumb mh-qp__thumb--composition"', false)
            ->assertSee('src="data:image/svg+xml;base64,', false);

        $this->post(route('quote.submit'), ['first_name' => 'Jan', 'last_name' => 'Jansen', 'email' => 'jan@example.com'])
            ->assertRedirect(route('quote.show'));

        $item = Submission::query()->sole()->items[0];
        $this->assertSame('samenstelling', $item['type']);
        $this->assertSame(2, $item['quantity']);
        $this->assertStringContainsString('Eigen samenstelling: 2 gekoppelde units', $item['title']);
        $this->assertStringContainsString('/unit-samensteller?samenstelling='.$share, $item['url']);
    }

    public function test_compositions_can_be_updated_and_removed(): void
    {
        $this->postJson(route('unit-composer.quote'), ['configuration' => ['size' => '3x3']]);
        $key = 'samenstelling:'.(new ComposedUnit(UnitConfiguration::normalize(['size' => '3x3'])))->key();

        $this->post(route('quote.update'), ['quantities' => [$key => 4]]);
        $this->get('/mijn-offerte/')->assertSee('value="4"', false);

        $this->post(route('quote.update'), ['remove' => $key]);
        $this->get('/mijn-offerte/')->assertSee('Je offerte is leeg');
    }

    public function test_a_composition_needs_a_valid_configuration(): void
    {
        $this->postJson(route('unit-composer.quote'), ['configuration' => ['size' => '12x3']])->assertUnprocessable();
        $this->postJson(route('unit-composer.quote'), ['configuration' => []])->assertUnprocessable();
    }
}
