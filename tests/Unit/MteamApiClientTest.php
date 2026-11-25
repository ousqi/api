<?php

namespace Tests\Unit;

use App\Services\MteamApiClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MteamApiClientTest extends TestCase
{
    public function test_returns_nested_successful_response_and_sends_expected_request(): void
    {
        $expected = [
            'data' => [
                'data' => [
                    ['id' => 7, 'name' => 'Arrival'],
                ],
            ],
        ];
        $this->configureMteam();
        Http::preventStrayRequests();
        Http::fake([
            'https://mteam.example.test/api/torrent/search' => Http::response($expected),
        ]);

        $response = $this->client()->search([
            'categories' => ['401'],
            'keyword' => 'Arrival',
            'pageSize' => 20,
        ]);

        $this->assertSame($expected, $response);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://mteam.example.test/api/torrent/search'
                && $request->hasHeader('x-api-key', 'test-api-key')
                && $request->data() === [
                    'visible' => 1,
                    'pageNumber' => 1,
                    'pageSize' => 20,
                    'categories' => ['401'],
                    'keyword' => 'Arrival',
                ];
        });
    }

    public function test_throws_when_mteam_returns_a_non_successful_response(): void
    {
        $this->configureMteam();
        Http::preventStrayRequests();
        Http::fake([
            'https://mteam.example.test/api/torrent/search' => Http::response(['message' => 'Unavailable'], 503),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MTeam 未返回可用的种子资料。');

        $this->client()->search([]);
    }

    public function test_throws_when_mteam_returns_a_malformed_payload(): void
    {
        $this->configureMteam();
        Http::preventStrayRequests();
        Http::fake([
            'https://mteam.example.test/api/torrent/search' => Http::response('not-json'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MTeam 未返回可用的种子资料。');

        $this->client()->search([]);
    }

    public function test_translates_a_failed_mteam_connection(): void
    {
        $this->configureMteam();
        Http::preventStrayRequests();
        Http::fake([
            'https://mteam.example.test/api/torrent/search' => Http::failedConnection(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MTeam 服务暂时不可用。');

        $this->client()->search([]);
    }

    private function client(): MteamApiClient
    {
        return new MteamApiClient($this->app->make(HttpFactory::class));
    }

    private function configureMteam(): void
    {
        config()->set('services.mteam.endpoint', 'https://mteam.example.test/');
        config()->set('services.mteam.key', 'test-api-key');
    }
}
