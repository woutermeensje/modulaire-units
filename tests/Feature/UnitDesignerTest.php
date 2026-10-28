<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Exceptions\UnitDesignFailed;
use App\Models\User;
use App\Services\UnitDesigner;
use App\Support\UnitConfiguration;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class UnitDesignerTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{request: RequestInterface}> */
    private array $sentRequests = [];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => 'test-key', 'services.anthropic.model' => 'claude-opus-5-5']);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_designer_sends_the_description_and_returns_a_normalized_design(): void
    {
        $this->fakeClaude([
            'size' => '6x3',
            'walls' => [
                'front' => ['window', 'window', 'door', 'empty', 'window', 'window', 'window'],
                'back' => ['empty', 'empty', 'empty', 'empty', 'empty', 'empty'],
                'left' => ['window', 'empty', 'empty'],
                'right' => ['empty', 'empty', 'empty'],
            ],
            'colors' => ['cladding' => '#C9CDCF', 'frame' => 'zwart'],
            'background' => 'site',
            'title' => ' Kantoorunit 6x3 ',
            'excerpt' => 'Lichte kantoorunit met vier ramen.',
            'meta_description' => str_repeat('a', 200),
        ]);

        $design = app(UnitDesigner::class)->design('6x3 kantoor, lichtgrijs', UnitConfiguration::defaults('3x3'));

        $this->assertSame(['window', 'window', 'door', 'empty', 'window', 'window'], $design['configuration']['walls']['front']);
        $this->assertSame('#c9cdcf', $design['configuration']['colors']['cladding']);
        $this->assertSame(UnitConfiguration::DEFAULT_COLORS['frame'], $design['configuration']['colors']['frame']);
        $this->assertSame('Kantoorunit 6x3', $design['title']);
        $this->assertSame(160, mb_strlen($design['meta_description']));

        $request = $this->sentRequests[0]['request'];
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('default', $body['fallbacks']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $request->getHeaderLine('anthropic-beta'));
        $this->assertStringContainsString('6x3 kantoor, lichtgrijs', $body['messages'][0]['content']);
        $this->assertStringContainsString('Huidige configuratie', $body['messages'][0]['content']);
    }

    public function test_designer_reports_a_refusal(): void
    {
        $this->fakeClaude([], 'refusal');

        $this->expectException(UnitDesignFailed::class);

        app(UnitDesigner::class)->design('iets onbegrijpelijks');
    }

    public function test_designer_rejects_invalid_json(): void
    {
        $this->expectException(UnitDesignFailed::class);

        app(UnitDesigner::class)->parse('geen json');
    }

    public function test_endpoint_returns_the_design_to_admins(): void
    {
        $design = ['configuration' => UnitConfiguration::defaults('3x3'), 'title' => 'Unit', 'excerpt' => 'Tekst', 'meta_description' => 'Meta'];
        $this->mock(UnitDesigner::class, function (MockInterface $mock) use ($design): void {
            $mock->shouldReceive('design')->once()->with('Een 3x3 unit', null)->andReturn($design);
        });

        $this->actingAs($this->admin)->postJson(route('admin.units.design'), ['description' => 'Een 3x3 unit'])
            ->assertOk()
            ->assertJson($design);

        $this->actingAs(User::factory()->create())->postJson(route('admin.units.design'), ['description' => 'Een 3x3 unit'])
            ->assertForbidden();
    }

    public function test_endpoint_explains_failures(): void
    {
        $this->mock(UnitDesigner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('design')->andThrow(new UnitDesignFailed('Probeer het anders te formuleren.'));
        });

        $this->actingAs($this->admin)->postJson(route('admin.units.design'), ['description' => 'x'])
            ->assertUnprocessable()
            ->assertJson(['message' => 'Probeer het anders te formuleren.']);

        $this->actingAs($this->admin)->postJson(route('admin.units.design'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');

        config(['services.anthropic.key' => null]);
        $this->actingAs($this->admin)->postJson(route('admin.units.design'), ['description' => 'x'])
            ->assertServiceUnavailable();
    }

    /**
     * @param  array<string, mixed>  $output
     */
    private function fakeClaude(array $output, string $stopReason = 'end_turn'): void
    {
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 'msg_test',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'claude-opus-5-5',
                'content' => $stopReason === 'refusal' ? [] : [['type' => 'text', 'text' => json_encode($output)]],
                'stop_reason' => $stopReason,
                'stop_sequence' => null,
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ])),
        ]));
        $handler->push(Middleware::history($this->sentRequests));

        $this->app->instance(Client::class, new Client(
            apiKey: 'test-key',
            requestOptions: ['transporter' => new GuzzleClient(['handler' => $handler]), 'maxRetries' => 0],
        ));
    }
}
