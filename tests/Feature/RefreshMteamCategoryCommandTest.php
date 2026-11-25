<?php

namespace Tests\Feature;

use App\Services\MovieTorrentCache;
use App\Services\MteamApiClient;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;
use Tests\TestCase;

class RefreshMteamCategoryCommandTest extends TestCase
{
    public function test_command_refreshes_only_the_requested_configured_category(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('search')
            ->with(['pageSize' => 100, 'categories' => ['401']])
            ->willReturn(['data' => [['name' => 'Movie', 'category' => '401']]]);
        $this->useArrayMteamCache($client);

        $this->artisan('mteam:refresh-category 401')
            ->expectsOutput('Cached 1 MTeam category 401 torrents.')
            ->assertExitCode(0);
    }

    public function test_command_without_category_ids_refreshes_every_configured_category_in_configuration_order(): void
    {
        $requestedCategoryIds = [];
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->exactly(21))
            ->method('search')
            ->willReturnCallback(function (array $filters) use (&$requestedCategoryIds): array {
                $this->assertArrayNotHasKey('category', $filters);
                $this->assertCount(1, $filters['categories']);
                $requestedCategoryIds[] = $filters['categories'][0];

                return ['data' => [[
                    'name' => 'Category '.$filters['categories'][0],
                    'category' => $filters['categories'][0],
                ]]];
            });
        $this->useArrayMteamCache($client);

        $this->artisan('mteam:refresh-category')
            ->expectsOutput('MTeam 分类刷新完成：成功 21，失败 0，已快取 21 个种子。')
            ->assertExitCode(0);

        $this->assertSame(
            ['419', '421', '439', '420', '401', '402', '438', '404', '435', '403', '405', '423', '429', '430', '432', '426', '431', '410', '424', '437', '412'],
            $requestedCategoryIds,
        );
    }

    public function test_command_refreshes_multiple_category_ids_in_their_supplied_order(): void
    {
        $requestedCategoryIds = [];
        $client = $this->clientRecordingRequestedCategoryIds($requestedCategoryIds, 3);
        $this->useArrayMteamCache($client);

        $this->artisan('mteam:refresh-category 401 419 412')
            ->expectsOutput('MTeam 分类刷新完成：成功 3，失败 0，已快取 3 个种子。')
            ->assertExitCode(0);

        $this->assertSame(['401', '419', '412'], $requestedCategoryIds);
    }

    public function test_command_deduplicates_supplied_category_ids_while_preserving_first_occurrence_order(): void
    {
        $requestedCategoryIds = [];
        $client = $this->clientRecordingRequestedCategoryIds($requestedCategoryIds, 3);
        $this->useArrayMteamCache($client);

        $this->artisan('mteam:refresh-category 401 419 401 412 419')
            ->expectsOutput('MTeam 分类刷新完成：成功 3，失败 0，已快取 3 个种子。')
            ->assertExitCode(0);

        $this->assertSame(['401', '419', '412'], $requestedCategoryIds);
    }

    public function test_command_rejects_non_numeric_category_ids_before_refreshing_any_category(): void
    {
        $this->assertPreflightValidationLeavesCachesUntouched('not-a-category');
    }

    public function test_command_rejects_unconfigured_category_ids_before_refreshing_any_category(): void
    {
        $this->assertPreflightValidationLeavesCachesUntouched('999');
    }

    public function test_command_rejects_invalid_multi_id_input_without_searching_or_resolving_the_cache_store(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->never())
            ->method('search');
        $cacheFactory = $this->createMock(CacheFactory::class);
        $cacheFactory->expects($this->never())
            ->method('store');
        $this->app->instance(MteamApiClient::class, $client);
        $this->app->instance(CacheFactory::class, $cacheFactory);

        $this->artisan('mteam:refresh-category 401 not-a-category')
            ->expectsOutput('分类 ID 无效或未在 MTeam 媒体分类中配置。')
            ->assertExitCode(1);
    }

    public function test_command_continues_after_a_category_failure_and_preserves_its_existing_cache(): void
    {
        $requestedCategoryIds = [];
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (array $filters) use (&$requestedCategoryIds): array {
                $categoryId = $filters['categories'][0];
                $requestedCategoryIds[] = $categoryId;

                if ($categoryId === '419') {
                    throw new RuntimeException('MTeam 服务暂时不可用。');
                }

                return ['data' => [[
                    'name' => 'Category '.$categoryId,
                    'category' => $categoryId,
                ]]];
            });
        $cache = $this->useArrayMteamCache($client);
        $existingPayload = [
            'torrents' => [['title' => 'Existing HD movie']],
            'refreshed_at' => '2026-11-24T00:00:00+00:00',
        ];
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'419', $existingPayload);

        $this->artisan('mteam:refresh-category 419 401')
            ->expectsOutput('MTeam 分类 419 快取刷新失败。')
            ->expectsOutput('MTeam 分类刷新完成：成功 1，失败 1，已快取 1 个种子。')
            ->assertExitCode(1);

        $this->assertSame(['419', '401'], $requestedCategoryIds);
        $this->assertSame($existingPayload, $cache->get(MovieTorrentCache::CACHE_KEY_PREFIX.'419'));
        $this->assertSame('Category 401', $cache->get(MovieTorrentCache::CACHE_KEY_PREFIX.'401')['torrents'][0]['title']);
    }

    private function assertPreflightValidationLeavesCachesUntouched(string $invalidCategoryId): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->never())
            ->method('search');
        $cache = $this->useArrayMteamCache($client);
        $existingPayload = [
            'torrents' => [['title' => 'Existing SD movie']],
            'refreshed_at' => '2026-11-24T00:00:00+00:00',
        ];
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', $existingPayload);

        $this->artisan("mteam:refresh-category 401 {$invalidCategoryId}")
            ->expectsOutput('分类 ID 无效或未在 MTeam 媒体分类中配置。')
            ->assertExitCode(1);

        $this->assertSame($existingPayload, $cache->get(MovieTorrentCache::CACHE_KEY_PREFIX.'401'));
    }

    /**
     * @param  array<int, string>  $requestedCategoryIds
     */
    private function clientRecordingRequestedCategoryIds(array &$requestedCategoryIds, int $expectedCalls): MteamApiClient
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->exactly($expectedCalls))
            ->method('search')
            ->willReturnCallback(function (array $filters) use (&$requestedCategoryIds): array {
                $categoryId = $filters['categories'][0];
                $requestedCategoryIds[] = $categoryId;

                return ['data' => [[
                    'name' => 'Category '.$categoryId,
                    'category' => $categoryId,
                ]]];
            });

        return $client;
    }

    private function useArrayMteamCache(MteamApiClient $client): Repository
    {
        config()->set('cache.stores.mteam_movies', ['driver' => 'array']);
        $this->app->instance(MteamApiClient::class, $client);

        return $this->app->make(CacheFactory::class)->store('mteam_movies');
    }
}
