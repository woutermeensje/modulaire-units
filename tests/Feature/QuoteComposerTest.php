<?php

namespace Tests\Feature;

use App\Mail\QuoteSent;
use App\Models\Quote;
use App\Models\User;
use App\Support\QuoteSpecification;
use App\Support\UnitConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('action="'.route('quote-composer.send').'"', false)
            ->assertSee('formaction="'.route('quote-composer.pdf-preview').'"', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="client_id"', false)
            ->assertSee('name="intro"', false)
            ->assertSee('name="images[plan]"', false)
            ->assertSee('Offerte versturen')
            ->assertDontSee('data-configurator-quote data-quote-url', false)
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

    /**
     * @return array<string, mixed>
     */
    private function quoteInput(User $client, array $overrides = []): array
    {
        return [
            'title' => 'Offerte portiersloge',
            'client_id' => $client->id,
            'intro' => "Beste Jan,\n\nHierbij de offerte.",
            'configuration' => [
                'size' => '6x3',
                'interior' => ['airco'],
                'furniture' => ['desk' => 1, 'chair' => 1],
                'connections' => ['socket' => 6],
                'tentative' => ['chair'],
                'steps' => '1',
                'walls' => ['front' => ['empty', 'window', 'window', 'empty', 'empty', 'empty'], 'right' => ['empty', 'hatch', 'empty'], 'left' => ['empty', 'door', 'empty']],
            ],
            'images' => [
                'front' => UploadedFile::fake()->image('front.jpg', 800, 500),
                'back' => UploadedFile::fake()->image('back.jpg', 800, 500),
                'plan' => UploadedFile::fake()->image('plan.png', 1200, 800),
            ],
            ...$overrides,
        ];
    }

    public function test_the_specification_lists_the_unit_its_openings_and_everything_ticked(): void
    {
        $sections = (new QuoteSpecification(UnitConfiguration::normalize($this->quoteInput(User::factory()->make())['configuration'])))->sections();

        $this->assertSame('Modulaire unit van 6 x 3 meter', $sections['Unit'][0]);
        $this->assertContains('1× raam (180 x 120 cm)', $sections['Ramen en deuren']);
        $this->assertContains('1× buitendeur (87,5 x 212,5 cm)', $sections['Ramen en deuren']);
        $this->assertContains('1× uitgifteluik (80 cm, wind- en waterdicht)', $sections['Ramen en deuren']);
        $this->assertSame(['Opstapje voor de deuren op de begane grond'], $sections['Toegang']);
        $this->assertSame(['Airco'], $sections['Klimaat, sanitair en keuken']);
        $this->assertSame(['6× wandcontactdoos'], $sections['Elektra en netwerk']);
        $this->assertSame(['1× bureau', '1× bureaustoel (optioneel)'], $sections['Meubilair']);
        $this->assertArrayNotHasKey('Type gebruik', $sections);
    }

    public function test_admins_can_preview_the_pdf_without_saving_or_sending(): void
    {
        Mail::fake();
        $client = User::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post(route('quote-composer.pdf-preview'), $this->quoteInput($client))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertSame(0, Quote::count());
        Mail::assertNothingSent();
    }

    public function test_admins_send_the_quote_to_the_client_with_the_pdf_attached(): void
    {
        Mail::fake();
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $client = User::factory()->create(['email' => 'jan@example.com']);

        $this->actingAs($admin)->post(route('quote-composer.send'), $this->quoteInput($client))
            ->assertRedirect(route('quote-composer'))
            ->assertSessionHas('status');

        $quote = Quote::sole();
        $this->assertSame('Offerte portiersloge', $quote->title);
        $this->assertTrue($quote->client->is($client));
        $this->assertTrue($quote->creator->is($admin));
        $this->assertNotNull($quote->sent_at);
        $this->assertSame(6, $quote->configuration['connections']['socket']);
        $this->assertSame(['front', 'back', 'plan'], array_keys($quote->images));
        Storage::disk('local')->assertExists($quote->images['plan']);

        Mail::assertSent(QuoteSent::class, function (QuoteSent $mail) use ($quote, $admin): bool {
            return $mail->hasTo('jan@example.com')
                && $mail->hasReplyTo($admin->email)
                && $mail->hasAttachment(Attachment::fromData(fn (): string => $mail->pdf, $quote->number().'.pdf')->withMime('application/pdf'))
                && str_starts_with($mail->pdf, '%PDF');
        });

        $this->actingAs($admin)->get(route('quote-composer'))->assertSee($quote->number())->assertSee(route('quotes.pdf', $quote), false);
        $this->actingAs($admin)->get(route('quotes.pdf', $quote))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_quote_needs_a_title_a_size_and_a_client_who_is_not_an_admin(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('quote-composer.send'), $this->quoteInput(User::factory()->create(), ['title' => '', 'configuration' => ['size' => '']]))
            ->assertSessionHasErrors(['title', 'configuration.size']);
        $this->actingAs($admin)->post(route('quote-composer.send'), $this->quoteInput($admin))
            ->assertSessionHasErrors('client_id');

        $this->assertSame(0, Quote::count());
        Mail::assertNothingSent();
    }

    public function test_customers_can_not_make_or_read_quotes(): void
    {
        $customer = User::factory()->create();
        $quote = Quote::factory()->create();

        $this->actingAs($customer)->post(route('quote-composer.send'), $this->quoteInput($customer))->assertForbidden();
        $this->actingAs($customer)->post(route('quote-composer.pdf-preview'), $this->quoteInput($customer))->assertForbidden();
        $this->actingAs($quote->client)->get(route('quotes.pdf', $quote))->assertForbidden();
    }

    public function test_admins_add_a_client_by_hand_without_an_account_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.opdrachtgevers.create'))
            ->assertOk()
            ->assertSee('name="first_name"', false)
            ->assertSee('name="last_name"', false)
            ->assertDontSee('name="password"', false);

        $this->actingAs($admin)->post(route('admin.opdrachtgevers.store'), [
            'first_name' => 'Jan',
            'last_name' => 'de Vries',
            'company_name' => 'Bouwbedrijf De Vries',
            'email' => 'jan@example.com',
            'phone' => '06 12345678',
        ])->assertRedirect();

        $client = User::query()->where('email', 'jan@example.com')->sole();
        $this->assertSame('Jan de Vries', $client->name);
        $this->assertSame('Jan', $client->firstName());
        $this->assertFalse($client->isAdmin());
        $this->assertNotEmpty($client->password);
        $this->assertFalse(Hash::check('', $client->password));

        $this->actingAs($admin)->get(route('quote-composer'))->assertSee('Bouwbedrijf De Vries – Jan de Vries (jan@example.com)');
        $this->actingAs($admin)->get(route('admin.opdrachtgevers.index'))->assertSee('Bouwbedrijf De Vries');
    }

    public function test_a_registered_client_shows_their_name_split_in_the_form(): void
    {
        $client = User::factory()->create(['name' => 'Piet van den Berg']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.opdrachtgevers.edit', $client->id))
            ->assertOk()
            ->assertSee('value="Piet"', false)
            ->assertSee('value="van den Berg"', false);
    }
}
