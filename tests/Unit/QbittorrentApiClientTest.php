<?php

namespace Tests\Unit;

use App\Services\QbittorrentApiClient;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class QbittorrentApiClientTest extends TestCase
{
    public function test_logs_in_and_adds_a_torrent(): void
    {
        $this->configureQbittorrent();
        Http::preventStrayRequests();
        Http::fake([
            'https://qbittorrent.example.test/api/v2/auth/login' => Http::response('Ok.', 200, ['Set-Cookie' => 'SID=test-session; HttpOnly']),
            'https://qbittorrent.example.test/api/v2/torrents/add' => Http::response('Ok.'),
        ]);

        $this->client()->addTorrent('magnet:?xt=urn:btih:123');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://qbittorrent.example.test/api/v2/auth/login'
            && $request->method() === 'POST'
            && $request->hasHeader('Referer', 'https://qbittorrent.example.test')
            && str_contains($request->body(), 'username=test-user')
            && str_contains($request->body(), 'password=test-password'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://qbittorrent.example.test/api/v2/torrents/add'
            && $request->method() === 'POST'
            && $request->hasHeader('Cookie', 'SID=test-session')
            && $request->hasHeader('Referer', 'https://qbittorrent.example.test')
            && str_contains($request->body(), 'magnet:?xt=urn:btih:123'));
    }

    public function test_uses_the_requested_configured_account(): void
    {
        config()->set('services.qbittorrent.accounts.archive', [
            'url' => 'https://archive-qbittorrent.example.test',
            'username' => 'archive-user',
            'password' => 'archive-password',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://archive-qbittorrent.example.test/api/v2/auth/login' => Http::response('Ok.', 200, ['Set-Cookie' => 'SID=archive-session; HttpOnly']),
            'https://archive-qbittorrent.example.test/api/v2/torrents/add' => Http::response('Ok.'),
        ]);

        $this->client()->addTorrent('https://tracker.example.test/download/1', 'archive', 'tv');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://archive-qbittorrent.example.test/api/v2/torrents/add'
            && $request->hasHeader('Cookie', 'SID=archive-session')
            && str_contains($request->body(), 'tv'));
    }

    public function test_accepts_a_custom_session_cookie_name_and_a_no_content_login_response(): void
    {
        $this->configureQbittorrent();
        Http::preventStrayRequests();
        Http::fake([
            'https://qbittorrent.example.test/api/v2/auth/login' => Http::response('', 204, ['set-cookie' => 'QBT_SID_8800=test-session; HttpOnly']),
            'https://qbittorrent.example.test/api/v2/torrents/add' => Http::response('Ok.'),
        ]);

        $this->client()->addTorrent('magnet:?xt=urn:btih:123');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://qbittorrent.example.test/api/v2/torrents/add'
            && $request->hasHeader('Cookie', 'QBT_SID_8800=test-session'));
    }

    public function test_accepts_an_empty_successful_add_torrent_response(): void
    {
        $this->configureQbittorrent();
        Http::preventStrayRequests();
        Http::fake([
            'https://qbittorrent.example.test/api/v2/auth/login' => Http::response('Ok.', 200, ['Set-Cookie' => 'SID=test-session; HttpOnly']),
            'https://qbittorrent.example.test/api/v2/torrents/add' => Http::response('', 200),
        ]);

        $this->client()->addTorrent('magnet:?xt=urn:btih:123');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://qbittorrent.example.test/api/v2/torrents/add');
    }

    public function test_returns_only_unfinished_torrents(): void
    {
        $this->configureQbittorrent();
        Http::preventStrayRequests();
        Http::fake([
            'https://qbittorrent.example.test/api/v2/auth/login' => Http::response('Ok.', 200, ['Set-Cookie' => 'SID=test-session; HttpOnly']),
            'https://qbittorrent.example.test/api/v2/torrents/info' => Http::response([
                ['name' => 'Downloading', 'category' => 'movie', 'progress' => 0.5, 'dlspeed' => 1024, 'eta' => 60, 'state' => 'downloading'],
                ['name' => 'Completed', 'category' => 'tv', 'progress' => 1, 'dlspeed' => 0, 'eta' => 0, 'state' => 'uploading'],
            ]),
        ]);

        $torrents = $this->client()->unfinishedTorrents('default');

        $this->assertSame([[
            'name' => 'Downloading',
            'category' => 'movie',
            'progress' => 0.5,
            'downloadSpeed' => 1024,
            'eta' => 60,
            'state' => 'downloading',
        ]], $torrents);
    }

    public function test_throws_when_the_account_is_not_configured(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('qBittorrent 账号未配置。');

        $this->client()->addTorrent('magnet:?xt=urn:btih:123', 'missing');
    }

    private function client(): QbittorrentApiClient
    {
        return new QbittorrentApiClient(
            $this->app->make(HttpFactory::class),
            $this->app->make(CacheRepository::class),
        );
    }

    private function configureQbittorrent(): void
    {
        config()->set('services.qbittorrent.accounts.default', [
            'url' => 'https://qbittorrent.example.test',
            'username' => 'test-user',
            'password' => 'test-password',
        ]);
    }
}
