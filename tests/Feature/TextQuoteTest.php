<?php

namespace Tests\Feature;

use App\Mail\TextQuoteSent;
use App\Models\Setting;
use App\Models\TextQuote;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TextQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_see_the_menu_item_and_the_form(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['company_name' => 'Bouwbedrijf Jansen']);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee(route('text-quotes.create'), false)->assertSee('Tekst offerte');

        $this->actingAs($admin)->get(route('text-quotes.create'))
            ->assertOk()
            ->assertSee('name="title"', false)
            ->assertSee('name="body"', false)
            ->assertSee('name="client_id"', false)
            ->assertSee('Bouwbedrijf Jansen')
            ->assertSee('formaction="'.route('text-quotes.preview').'"', false);
    }

    public function test_only_admins_can_use_it(): void
    {
        $this->get(route('text-quotes.create'))->assertRedirect(route('login'));

        $client = User::factory()->create();
        $this->actingAs($client)->get(route('text-quotes.create'))->assertForbidden();
        $this->actingAs($client)->post(route('text-quotes.send'), ['title' => 'x', 'body' => '<p>x</p>', 'client_id' => $client->id])->assertForbidden();
        $this->actingAs($client)->get(route('dashboard'))->assertDontSee(route('text-quotes.create'), false);
    }

    public function test_the_client_gets_a_mail_with_the_pdf(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $client = User::factory()->create(['email' => 'klant@example.com']);

        $this->actingAs($admin)->post(route('text-quotes.send'), [
            'title' => 'Offerte twee kantoorunits',
            'body' => '<p>Beste klant,</p><ol><li data-list="bullet">Airco</li><li data-list="bullet">Toilet</li></ol><script>alert(1)</script>',
            'client_id' => $client->id,
        ])->assertRedirect(route('text-quotes.create'))->assertSessionHas('status');

        $quote = TextQuote::query()->sole();
        $this->assertSame($client->id, $quote->client_id);
        $this->assertSame($admin->id, $quote->created_by);
        $this->assertNotNull($quote->sent_at);
        $this->assertSame('<p>Beste klant,</p><ul><li>Airco</li><li>Toilet</li></ul>', $quote->body);

        Mail::assertSent(TextQuoteSent::class, function (TextQuoteSent $mail) use ($quote): bool {
            return $mail->hasTo('klant@example.com')
                && $mail->hasReplyTo($quote->creator->email)
                && str_starts_with($mail->pdf, '%PDF')
                && $mail->attachments()[0]->as === $quote->number().'.pdf';
        });

        $this->actingAs($admin)->get(route('text-quotes.create'))->assertSee($quote->number())->assertSee('Offerte twee kantoorunits');
        $this->actingAs($admin)->get(route('text-quotes.pdf', $quote))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sending_needs_a_title_a_text_and_a_client(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('text-quotes.send'), ['title' => '', 'body' => '<p><br></p>', 'client_id' => ''])
            ->assertSessionHasErrors(['title', 'body', 'client_id']);

        // An admin is not a client.
        $this->actingAs($admin)->post(route('text-quotes.send'), ['title' => 'Offerte', 'body' => '<p>Tekst</p>', 'client_id' => $admin->id])
            ->assertSessionHasErrors('client_id');

        $this->assertSame(0, TextQuote::query()->count());
        Mail::assertNothingSent();
    }

    public function test_the_preview_shows_the_pdf_without_saving_or_sending(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('text-quotes.preview'), ['title' => 'Offerte', 'body' => '<p>Tekst</p>'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame(0, TextQuote::query()->count());
        Mail::assertNothingSent();
    }

    public function test_the_pdf_has_the_company_details_at_the_top(): void
    {
        $quote = TextQuote::factory()->create(['title' => 'Offerte kantine', 'body' => '<p>De tekst</p>']);

        $html = view('pdf.text-quote', ['quote' => $quote, 'logo' => 'data:image/png;base64,AAAA'])->render();

        $this->assertStringContainsString('<img src="data:image/png;base64,AAAA"', $html);
        $this->assertStringContainsString('informatie@modulairehuisvesting.nl', $html);
        $this->assertStringContainsString(Setting::get('address'), $html);
        $this->assertStringContainsString(Setting::get('phone'), $html);
        $this->assertStringContainsString($quote->number(), $html);
        $this->assertStringContainsString('<p>De tekst</p>', $html);
    }

    public function test_rich_text_keeps_only_the_editor_formats(): void
    {
        $this->assertSame(
            '<h2>Kop</h2><p><strong>vet</strong> <em>schuin</em> <a href="https://example.com">link</a> <a>slecht</a></p><ol><li>een</li></ol><ul><li class="ql-indent-1">punt</li></ul>',
            RichText::clean('<h2 style="color:red">Kop</h2><p onclick="x()"><strong>vet</strong> <em>schuin</em> <a href="https://example.com" target="_blank">link</a> <a href="javascript:alert(1)">slecht</a></p><ol><li data-list="ordered">een</li><li data-list="bullet" class="ql-indent-1 foo">punt</li></ol><img src="x"><!-- c -->'),
        );
        $this->assertTrue(RichText::isEmpty('<p><br></p>'));
        $this->assertTrue(RichText::isEmpty('<p>&nbsp;</p>'));
        $this->assertFalse(RichText::isEmpty('<p>Tekst</p>'));
    }
}
