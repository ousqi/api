<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TtsPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Auth::viaRequest('test-token', fn ($request) => $request->bearerToken() === 'valid-test-token' ? new User : null);
        config(['auth.guards.api.driver' => 'test-token']);
    }

    public function test_preview_requires_authentication(): void
    {
        $this->postJson('/api/tts/preview', [])->assertUnauthorized();
    }

    public function test_tts_routes_use_the_stateless_api_middleware_group(): void
    {
        $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('/api/tts/preview', 'POST'));

        $this->assertContains('api', $route->gatherMiddleware());
        $this->assertNotContains('web', $route->gatherMiddleware());
    }

    public function test_local_preview_does_not_require_a_login(): void
    {
        app()['env'] = 'local';
        config(['services.azure_speech.key' => 'test-key', 'services.azure_speech.region' => 'eastus']);
        Http::fake(['*' => Http::response('audio-data', 200, ['Content-Type' => 'audio/mpeg'])]);

        $this->getJson('/api/tts/voices')->assertOk();
        $this->postJson('/api/tts/preview', [
            'text' => 'apple', 'voice' => 'us_female', 'speed' => 'normal',
        ])->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
    }

    public function test_preview_escapes_text_and_returns_audio(): void
    {
        config(['services.azure_speech.key' => 'test-key', 'services.azure_speech.region' => 'eastus']);
        Http::fake(['*' => Http::response('audio-data', 200, ['Content-Type' => 'audio/mpeg'])]);

        $response = $this->withToken('valid-test-token')->postJson('/api/tts/preview', [
            'text' => 'one & two', 'voice' => 'us_female', 'speed' => 'slow',
        ]);

        $response->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
        $this->assertSame('audio-data', $response->getContent());
        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'one &amp; two') && str_contains($request->body(), 'rate="-20%"'));
    }

    public function test_invalid_voice_is_rejected(): void
    {
        Http::fake();
        $this->withToken('valid-test-token')->postJson('/api/tts/preview', [
            'text' => 'apple', 'voice' => 'invalid', 'speed' => 'normal',
        ])->assertUnprocessable()->assertJsonValidationErrors('voice');
        Http::assertNothingSent();
    }

    public function test_provider_errors_are_not_exposed_as_audio(): void
    {
        config(['services.azure_speech.key' => 'test-key', 'services.azure_speech.region' => 'eastus']);
        Http::fake(['*' => Http::response('secret provider details', 401)]);

        $this->withToken('valid-test-token')->postJson('/api/tts/preview', [
            'text' => 'apple', 'voice' => 'us_female', 'speed' => 'normal',
        ])->assertStatus(502)->assertDontSee('secret provider details');
    }
}
