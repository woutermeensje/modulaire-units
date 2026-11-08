<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Services\ContentTranslator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class TranslateSiteContentTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{request: RequestInterface}> */
    private array $sentRequests = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-opus-5-5']);
    }

    public function test_pages_are_translated_with_slug_path_hero_and_seo_texts(): void
    {
        $page = Page::factory()->create([
            'title' => 'Over ons',
            'slug' => 'over-ons',
            'content' => '<p>Wij zijn <a href="/contact/">bereikbaar</a>.</p> [team columns="3"]',
            'meta_title' => null,
            'meta_description' => null,
            'hero' => ['title' => 'Welkom', 'image' => 'media/hero.jpg', 'button_url' => '/units/'],
        ]);

        $this->fakeClaude([[
            $page->id.'__title' => 'About us',
            $page->id.'__slug' => 'About Us',
            $page->id.'__content' => '<p>We are <a href="/contact/">available</a>.</p> [team columns="3"]',
            $page->id.'__excerpt' => '',
            $page->id.'__meta_title' => 'About us | Modular units',
            $page->id.'__meta_description' => 'Get to know the team behind ModulaireHuisvesting.nl.',
            $page->id.'__hero_title' => 'Welcome',
        ]]);

        $this->artisan('site:translate', ['--type' => ['pages']])->assertSuccessful();

        $page->refresh();
        $this->assertSame('About us', $page->translation('en', 'title'));
        $this->assertSame('about-us', $page->translation('en', 'slug'));
        $this->assertSame('about-us', $page->translation('en', 'path'));
        $this->assertSame('Get to know the team behind ModulaireHuisvesting.nl.', $page->translation('en', 'meta_description'));
        $this->assertSame(['title' => 'Welcome', 'image' => 'media/hero.jpg', 'button_url' => '/units/'], $page->translation('en', 'hero'));
        $this->assertSame('Over ons', $page->title);

        $prompt = json_decode((string) $this->sentRequests[0]['request']->getBody(), true);
        $this->assertStringContainsString('Wij zijn', $prompt['messages'][0]['content']);
        $this->assertArrayHasKey($page->id.'__meta_description', $prompt['output_config']['format']['schema']['properties']);
    }

    public function test_a_translation_that_breaks_the_html_is_retried_and_then_rejected(): void
    {
        $page = Page::factory()->create(['title' => 'Contact', 'slug' => 'contact', 'content' => '<p><strong>Bel</strong> ons</p>']);
        $broken = [$page->id.'__title' => 'Contact', $page->id.'__slug' => 'contact', $page->id.'__content' => '<p>Call us</p>', $page->id.'__meta_title' => 'Contact', $page->id.'__meta_description' => 'Contact us'];

        $this->fakeClaude([$broken, [$page->id.'__content' => '<div>Call us</div>']]);

        $this->artisan('site:translate', ['--type' => ['pages']])->assertFailed();

        $this->assertCount(2, $this->sentRequests);
        $this->assertFalse($page->refresh()->hasTranslation('en'));
    }

    public function test_short_texts_are_translated_together_and_translated_records_are_skipped(): void
    {
        $menu = MenuItem::factory()->create(['label' => 'Huren', 'url' => '/huren/']);
        $other = MenuItem::factory()->create(['label' => 'Kopen', 'url' => '/kopen/']);
        MenuItem::factory()->create(['label' => 'Contact', 'translations' => ['en' => ['label' => 'Contact']]]);

        $this->fakeClaude([[$menu->id.'__label' => 'Rent', $other->id.'__label' => 'Buy']]);

        $this->artisan('site:translate', ['--type' => ['menu']])->assertSuccessful();

        $this->assertCount(1, $this->sentRequests);
        $this->assertSame('Rent', $menu->refresh()->translation('en', 'label'));
        $this->assertNull($menu->translation('en', 'url'));
        $this->assertSame('Buy', $other->refresh()->translation('en', 'label'));
    }

    public function test_settings_are_translated_into_their_english_setting(): void
    {
        Setting::put('footer_about', 'Wij leveren units.');

        $this->fakeClaude([[
            'site_tagline' => 'See all modular units!',
            'footer_about' => 'We supply units.',
            'discount_popup_title' => 'Get a discount',
            'discount_popup_text' => 'Mention this code.',
        ]]);

        $this->artisan('site:translate', ['--type' => ['settings']])->assertSuccessful();

        $this->assertSame('We supply units.', Setting::get('footer_about_en'));
    }

    public function test_the_command_needs_an_api_key(): void
    {
        config(['services.anthropic.key' => null]);

        $this->assertFalse(ContentTranslator::isConfigured());
        $this->artisan('site:translate')->assertFailed();
    }

    /**
     * @param  list<array<string, string>>  $outputs  One JSON answer per expected request.
     */
    private function fakeClaude(array $outputs): void
    {
        $handler = HandlerStack::create(new MockHandler(array_map(fn (array $output): Response => new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => json_encode($output)]],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ])), $outputs)));
        $handler->push(Middleware::history($this->sentRequests));

        $this->app->instance(Client::class, new Client(
            apiKey: 'test-key',
            requestOptions: ['transporter' => new GuzzleClient(['handler' => $handler]), 'maxRetries' => 0],
        ));
    }
}
