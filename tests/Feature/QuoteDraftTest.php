<?php

namespace Tests\Feature;

use App\Mail\QuoteSent;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Quotes saved as a draft in /account/offerte-samenstellen/: kept without being sent, opened again to work on, and sent
 * later as the same quote.
 */
class QuoteDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_draft_is_saved_without_a_client_or_prices_and_nothing_is_sent(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('quote-composer.draft'), [
            'title' => 'Offerte pasuitgiftepost',
            'locale' => 'nl',
            'client_id' => '',
            'kind' => 'rent',
            'configuration' => ['size' => '6x3'],
            'specifications' => ['climate' => 'Split-airco'],
            'lines' => [['description' => 'Huur unit', 'quantity' => '1', 'price' => '', 'period' => 'week', 'status' => 'price']],
            'internal_notes' => 'Nog navragen bij de leverancier',
            'images' => ['front' => UploadedFile::fake()->image('front.jpg')],
        ]);

        $quote = Quote::sole();
        $response->assertRedirect(route('quote-composer', ['concept' => $quote->id]));
        $this->assertNull($quote->sent_at);
        $this->assertNull($quote->client_id);
        $this->assertNull($quote->images);
        $this->assertSame('Split-airco', $quote->specifications['climate']);
        $this->assertNull($quote->lines[0]['price']);
        $this->assertSame($admin->id, $quote->created_by);
        Mail::assertNothingSent();
    }

    public function test_a_draft_still_needs_a_title(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('quote-composer.draft'), ['title' => '', 'locale' => 'nl', 'configuration' => ['size' => '6x3']])
            ->assertSessionHasErrors('title');

        $this->assertSame(0, Quote::count());
    }

    public function test_an_opened_draft_is_filled_in_and_saving_again_updates_it(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = Quote::factory()->draft()->create(['title' => 'Offerte kantoorunit', 'client_id' => null, 'reference' => 'Bouwkeet']);

        $this->actingAs($admin)->get(route('quote-composer', ['concept' => $draft->id]))
            ->assertOk()
            ->assertSee('name="draft_id" value="'.$draft->id.'"', false)
            ->assertSee('value="Offerte kantoorunit"', false)
            ->assertSee('value="Bouwkeet"', false)
            ->assertSee('Opslaan als concept');

        $this->actingAs($admin)->post(route('quote-composer.draft'), [
            'draft_id' => $draft->id,
            'title' => 'Offerte kantoorunit 6 x 3',
            'locale' => 'nl',
            'configuration' => ['size' => '6x3'],
        ])->assertRedirect(route('quote-composer', ['concept' => $draft->id]));

        $this->assertSame(1, Quote::count());
        $this->assertSame('Offerte kantoorunit 6 x 3', $draft->fresh()->title);
        $this->assertNull($draft->fresh()->reference);
    }

    public function test_sending_a_draft_sends_that_same_quote(): void
    {
        Mail::fake();
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $client = User::factory()->create();
        $draft = Quote::factory()->draft()->create(['client_id' => null]);
        $number = $draft->number();

        $this->actingAs($admin)->post(route('quote-composer.send'), [
            'draft_id' => $draft->id,
            'title' => 'Offerte portiersloge',
            'locale' => 'nl',
            'client_id' => $client->id,
            'configuration' => ['size' => '6x3'],
            'images' => ['plan' => UploadedFile::fake()->image('plan.png')],
        ])->assertRedirect(route('quotes.index'));

        $quote = Quote::sole();
        $this->assertSame($number, $quote->number());
        $this->assertNotNull($quote->sent_at);
        $this->assertSame($client->id, $quote->client_id);
        Storage::disk('local')->assertExists("quotes/{$quote->id}/plan.png");
        Mail::assertSent(QuoteSent::class);
    }

    public function test_a_sent_quote_can_not_be_opened_or_overwritten_as_a_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $sent = Quote::factory()->create(['title' => 'Verstuurd']);

        $this->actingAs($admin)->get(route('quote-composer', ['concept' => $sent->id]))->assertNotFound();
        $this->actingAs($admin)->post(route('quote-composer.draft'), [
            'draft_id' => $sent->id,
            'title' => 'Overschreven',
            'locale' => 'nl',
            'configuration' => ['size' => '6x3'],
        ])->assertSessionHasErrors('draft_id');

        $this->assertSame('Verstuurd', $sent->fresh()->title);
    }

    public function test_drafts_have_their_own_tab_in_the_overview_and_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = Quote::factory()->draft()->create(['title' => 'Concept bouwkeet', 'client_id' => null]);
        $sent = Quote::factory()->create(['title' => 'Verstuurde portiersloge']);

        $this->actingAs($admin)->get(route('quotes.index'))
            ->assertSee('Verstuurde portiersloge')
            ->assertDontSee('Concept bouwkeet')
            ->assertSee('Concepten (1)');

        $this->actingAs($admin)->get(route('quotes.index', ['status' => 'concept']))
            ->assertOk()
            ->assertSee('Concept bouwkeet')
            ->assertSee('Nog niet gekozen')
            ->assertSee(route('quote-composer', ['concept' => $draft->id]), false)
            ->assertDontSee('Verstuurde portiersloge');

        $this->actingAs($admin)->get(route('quote-composer'))
            ->assertSee('Verstuurde offertes bekijken (1)')
            ->assertSee('Concepten (1)');

        $this->actingAs($admin)->get(route('quotes.pdf', $draft))->assertNotFound();

        $this->actingAs($admin)->delete(route('quotes.destroy', $draft))->assertRedirect(route('quotes.index', ['status' => 'concept']));
        $this->assertModelMissing($draft);

        $this->actingAs($admin)->delete(route('quotes.destroy', $sent))->assertForbidden();
        $this->assertModelExists($sent);

        $this->actingAs(User::factory()->create())->post(route('quote-composer.draft'), ['title' => 'x'])->assertForbidden();
    }
}
