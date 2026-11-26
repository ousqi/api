<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class QbittorrentTorrentTest extends TestCase
{
    public function test_adds_a_magnet_torrent(): void
    {
        app()['env'] = 'local';
        config()->set('services.qbittorrent.accounts.default', [
            'url' => 'https://qbittorrent.example.test',
            'username' => 'test-user',
            'password' => 'test-password',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://qbittorrent.example.test/api/v2/auth/login' => Http::response('Ok.', 200, ['Set-Cookie' => 'SID=test-session; HttpOnly']),
            'https://qbittorrent.example.test/api/v2/torrents/add' => Http::response('Ok.'),
        ]);

        $this->postJson('/api/qbittorrent/torrents', [
            'url' => 'magnet:?xt=urn:btih:123',
        ])->assertCreated()->assertJson(['message' => '种子已添加到 qBittorrent。']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://qbittorrent.example.test/api/v2/torrents/add'
            && str_contains($request->body(), 'magnet:?xt=urn:btih:123'));
    }

    public function test_rejects_an_unsupported_torrent_url(): void
    {
        app()['env'] = 'local';
        Http::preventStrayRequests();

        $this->postJson('/api/qbittorrent/torrents', [
            'url' => 'ftp://tracker.example.test/download/1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['url']);

        Http::assertNothingSent();
    }

    public function test_generates_an_mteam_download_url_before_adding_the_torrent(): void
    {
        app()['env'] = 'local';
        config()->set('services.mteam.endpoint', 'https://mteam.example.test');
        config()->set('services.mteam.key', 'mteam-api-key');
        config()->set('services.qbittorrent.accounts.default', [
            'name' => '客厅 NAS',
            'url' => 'https://qbittorrent.example.test',
            'username' => 'test-user',
            'password' => 'test-password',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://mteam.example.test/api/torrent/genDlToken?id=1125397' => Http::response([
                'code' => '0',
                'data' => 'https://mteam.example.test/api/rss/dlv2?sign=token',
            ]),
            'https://qbittorrent.example.test/api/v2/auth/login' => Http::response('Ok.', 200, ['Set-Cookie' => 'SID=test-session; HttpOnly']),
            'https://qbittorrent.example.test/api/v2/torrents/add' => Http::response('Ok.'),
        ]);

        $this->withSession([
            '_token' => 'csrf-token',
            'qbittorrent.download_authorized_until' => now()->addMinutes(30)->getTimestamp(),
        ])
            ->withHeader('X-CSRF-TOKEN', 'csrf-token')
            ->postJson('/api/qbittorrent/mteam-torrents', [
                'torrent' => '1125397',
                'account' => 'default',
                'category' => 'game',
            ])->assertCreated();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://mteam.example.test/api/torrent/genDlToken?id=1125397'
            && $request->hasHeader('x-api-key', 'mteam-api-key'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://qbittorrent.example.test/api/v2/torrents/add'
            && str_contains($request->body(), 'https://mteam.example.test/api/rss/dlv2?sign=token')
            && str_contains($request->body(), 'game'));
    }

    public function test_download_authorization_is_valid_for_thirty_minutes(): void
    {
        RateLimiter::clear('qbittorrent-download-authorize:127.0.0.1');
        config()->set('services.qbittorrent.download_auth.password', 'download-password');

        $response = $this->withSession(['_token' => 'csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'csrf-token')
            ->postJson('/qbittorrent-download/authorize', ['password' => 'download-password']);

        $response->assertOk();
        $this->assertGreaterThan(now()->addMinutes(29)->getTimestamp(), $response->json('authorizedUntil'));
        $this->assertLessThanOrEqual(now()->addMinutes(30)->getTimestamp(), $response->json('authorizedUntil'));
        $this->assertSame($response->json('authorizedUntil'), session('qbittorrent.download_authorized_until'));
    }
}
