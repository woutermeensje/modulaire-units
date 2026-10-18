<?php

namespace Tests\Feature;

use App\Mail\SubmissionReceived;
use App\Models\Submission;
use App\Models\Unit;
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
}
