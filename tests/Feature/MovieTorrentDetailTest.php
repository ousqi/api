<?php

namespace Tests\Feature;

use App\Services\MteamApiClient;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MovieTorrentDetailTest extends TestCase
{
    public function test_it_returns_a_safe_display_payload_for_a_matching_configured_category(): void
    {
        RateLimiter::clear('mteam-detail:127.0.0.1');
        config()->set('cache.stores.mteam_movies', ['driver' => 'array']);
        config()->set('services.qbittorrent.accounts.default', [
            'name' => '客厅 NAS',
            'url' => 'https://qbittorrent.example.test',
            'username' => 'test-user',
            'password' => 'test-password',
        ]);
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('detail')
            ->with('1125397')
            ->willReturn([
                'id' => '1125397',
                'category' => '421',
                'name' => 'King of Beggars 1992',
                'smallDescr' => '武状元苏乞儿',
                'descr' => '<script>alert("xss")</script> 影片简介',
                'createdDate' => '2026-01-29 17:34:36',
                'size' => '48820387840',
                'numfiles' => '42',
                'countries' => ['中国香港'],
                'standard' => '1080p',
                'videoCodec' => 'AVC',
                'audioCodec' => 'LPCM 2.0',
                'hasChineseSubtitle' => true,
                'labels' => 'DIY',
                'douban' => 'https://movie.douban.com/subject/1297863/',
                'imdb' => 'https://www.imdb.com/title/tt0100963/',
                'mediainfo' => 'General\nFormat: Blu-ray',
                'infoHash' => 'must-not-be-exposed',
                'status' => [
                    'seeders' => '12',
                    'leechers' => '3',
                    'timesCompleted' => '99',
                    'views' => '100',
                    'discount' => 'FREE',
                ],
            ]);
        $this->app->instance(MteamApiClient::class, $client);

        $this->getJson('/torrents/421/1125397')
            ->assertOk()
            ->assertJsonPath('title', '武状元苏乞儿')
            ->assertJsonPath('name', 'King of Beggars 1992')
            ->assertJsonPath('description', '<script>alert("xss")</script> 影片简介')
            ->assertJsonPath('torrent', '1125397')
            ->assertJsonPath('officialUrl', 'https://kp.m-team.cc/detail/1125397')
            ->assertJsonPath('downloadCategory', 'movie')
            ->assertJsonPath('downloadAccounts.0', ['key' => 'default', 'name' => '客厅 NAS'])
            ->assertJsonMissing(['infoHash', 'metadata', 'stats', 'links', 'mediaInfo']);
    }

    public function test_it_hides_a_torrent_when_its_returned_category_does_not_match_the_route(): void
    {
        RateLimiter::clear('mteam-detail:127.0.0.1');
        config()->set('cache.stores.mteam_movies', ['driver' => 'array']);
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('detail')
            ->with('1125398')
            ->willReturn(['category' => '419']);
        $this->app->instance(MteamApiClient::class, $client);

        $this->getJson('/torrents/421/1125398')->assertNotFound();
    }

    public function test_it_does_not_expose_adult_torrent_details_without_an_authorized_session(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->never())->method('detail');
        $this->app->instance(MteamApiClient::class, $client);

        $this->getJson('/torrents/429/1125397')->assertForbidden();
    }
}
