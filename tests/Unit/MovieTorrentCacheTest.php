<?php

namespace Tests\Unit;

use App\Services\MovieTorrentCache;
use App\Services\MteamApiClient;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class MovieTorrentCacheTest extends TestCase
{
    public function test_successful_category_refresh_caches_only_the_normalized_category_payload(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('search')
            ->with(['pageSize' => 100, 'categories' => ['401']])
            ->willReturn([
                'data' => [[
                    'id' => 7,
                    'name' => 'Arrival',
                    'posterUrl' => 'https://images.example.test/arrival.jpg',
                    'createdDate' => '2026-11-24 12:00:00',
                    'smallDescr' => 'Sci-fi drama',
                    'category' => 401,
                ], [
                    'id' => 8,
                    'name' => 'Wrong category',
                    'category' => '419',
                ]],
            ]);
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'419', [
            'torrents' => [['title' => 'Existing HD movie']],
            'refreshed_at' => '2026-11-24T00:00:00+00:00',
        ]);
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        $torrents = $service->refreshCategory('401');

        $this->assertSame(['Arrival'], array_column($torrents, 'title'));
        $this->assertSame(401, $torrents[0]['raw']['category']);
        $this->assertSame('Arrival', $service->moviesForCategories(['401'])[0]['title']);
        $this->assertSame([['title' => 'Existing HD movie']], $cache->get(MovieTorrentCache::CACHE_KEY_PREFIX.'419')['torrents']);
    }

    public function test_failed_category_refresh_preserves_that_categorys_existing_cache(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('search')
            ->willThrowException(new RuntimeException('MTeam 服务暂时不可用。'));
        $cache = $this->cache();
        $existingPayload = [
            'torrents' => [['title' => 'Existing movie']],
            'refreshed_at' => '2026-11-24T00:00:00+00:00',
        ];
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', $existingPayload);
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        try {
            $service->refreshCategory('401');
            $this->fail('Expected the category refresh to fail.');
        } catch (RuntimeException) {
            $this->assertSame($existingPayload, $cache->get(MovieTorrentCache::CACHE_KEY_PREFIX.'401'));
        }
    }

    public function test_category_refresh_with_no_matching_items_preserves_that_categorys_existing_cache(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('search')
            ->with(['pageSize' => 100, 'categories' => ['401']])
            ->willReturn(['data' => [['id' => 7, 'name' => 'Wrong category', 'category' => '419']]]);
        $cache = $this->cache();
        $existingPayload = [
            'torrents' => [['title' => 'Existing movie']],
            'refreshed_at' => '2026-11-24T00:00:00+00:00',
        ];
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', $existingPayload);
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        try {
            $service->refreshCategory('401');
            $this->fail('Expected the category refresh to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('MTeam 未返回请求分类的可展示种子。', $exception->getMessage());
            $this->assertSame($existingPayload, $cache->get(MovieTorrentCache::CACHE_KEY_PREFIX.'401'));
        }
    }

    public function test_adult_category_refresh_sends_the_configured_adult_mode(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('search')
            ->with(['pageSize' => 100, 'categories' => ['431'], 'mode' => 'adult'])
            ->willReturn(['data' => [['id' => 7, 'name' => 'Adult movie', 'category' => '431']]]);
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        $service->refreshCategory('431');
    }

    public function test_regular_category_refresh_does_not_send_an_adult_mode(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('search')
            ->with(['pageSize' => 100, 'categories' => ['401']])
            ->willReturn(['data' => [['id' => 7, 'name' => 'Movie', 'category' => '401']]]);
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        $service->refreshCategory('401');
    }

    public function test_movie_search_sends_the_configured_movie_mode_and_returns_matching_results(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('search')
            ->with([
                'pageSize' => 100,
                'keyword' => '周星驰',
                'categories' => ['419', '421', '439', '420', '401'],
                'mode' => 'movie',
            ])
            ->willReturn(['data' => [
                ['id' => 7, 'name' => '功夫', 'category' => '419'],
                ['id' => 8, 'name' => '电视剧结果', 'category' => '402'],
            ]]);
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        $torrents = $service->search('周星驰', ['419', '421', '439', '420', '401']);

        $this->assertSame(['功夫'], array_column($torrents, 'title'));
    }

    public function test_tv_search_sends_the_configured_tvshow_mode(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('search')
            ->with([
                'pageSize' => 100,
                'keyword' => '周星驰',
                'categories' => ['402'],
                'mode' => 'tvshow',
            ])
            ->willReturn(['data' => [['id' => 7, 'name' => '西游降魔篇', 'category' => '402']]]);
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        $torrents = $service->search('周星驰', ['402']);

        $this->assertSame(['西游降魔篇'], array_column($torrents, 'title'));
    }

    public function test_adult_refresh_with_no_matching_items_preserves_its_existing_cache(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('search')
            ->with(['pageSize' => 100, 'categories' => ['431'], 'mode' => 'adult'])
            ->willReturn(['data' => [['id' => 7, 'name' => 'Wrong category', 'category' => '432']]]);
        $cache = $this->cache();
        $existingPayload = [
            'torrents' => [['title' => 'Existing adult movie']],
            'refreshed_at' => '2026-11-24T00:00:00+00:00',
        ];
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'431', $existingPayload);
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        try {
            $service->refreshCategory('431');
            $this->fail('Expected the category refresh to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('MTeam 未返回请求分类的可展示种子。', $exception->getMessage());
            $this->assertSame($existingPayload, $cache->get(MovieTorrentCache::CACHE_KEY_PREFIX.'431'));
        }
    }

    public function test_refreshing_an_unconfigured_category_throws_an_invalid_argument_exception(): void
    {
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->never())
            ->method('search');
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        $this->expectException(InvalidArgumentException::class);

        $service->refreshCategory('999');
    }

    public function test_category_aggregation_reads_only_the_requested_child_cache_keys(): void
    {
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => [['title' => 'Standard definition']],
            'refreshed_at' => '2026-11-24T11:00:00+00:00',
        ]);
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'419', [
            'torrents' => [['title' => 'High definition']],
            'refreshed_at' => '2026-11-24T12:00:00+00:00',
        ]);
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'402', [
            'torrents' => [['title' => 'TV show']],
            'refreshed_at' => '2026-11-24T13:00:00+00:00',
        ]);
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->never())
            ->method('search');
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        $movies = $service->moviesForCategories(['401', '419']);

        $this->assertSame(['Standard definition', 'High definition'], array_column($movies, 'title'));
        $this->assertSame('2026-11-24T12:00:00+00:00', $service->refreshedAtForCategories(['401', '419']));
    }

    public function test_category_cache_items_are_sorted_by_publish_time_with_unparseable_values_last_and_source_order_preserved_for_ties(): void
    {
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => [
                ['title' => 'ISO date', 'time' => '2024-11-24T12:00:00+00:00'],
                ['title' => 'Missing time'],
                ['title' => 'Unix seconds', 'time' => '1763985600'],
                ['title' => 'Datetime', 'time' => '2027-11-24 12:00:00'],
                ['title' => 'Unix milliseconds', 'time' => '1795521600000'],
                ['title' => 'Invalid time', 'time' => 'not-a-date'],
                ['title' => 'Matching ISO date', 'time' => '2024-11-24T12:00:00Z'],
                ['title' => 'Invalid calendar date', 'time' => '2024-02-31'],
            ],
        ]);
        $service = new MovieTorrentCache($this->createMock(MteamApiClient::class), $this->app->make(CacheFactory::class));

        $movies = $service->moviesForCategories(['401']);

        $this->assertSame([
            'Datetime',
            'Unix milliseconds',
            'Unix seconds',
            'ISO date',
            'Matching ISO date',
            'Missing time',
            'Invalid time',
            'Invalid calendar date',
        ], array_column($movies, 'title'));
    }

    public function test_invalid_calendar_timestamps_sort_last_while_valid_iso_offsets_and_fractions_are_preserved(): void
    {
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => [
                ['title' => 'Invalid leap day', 'time' => '2023-02-29T12:00:00+00:00'],
                ['title' => 'Offset with fraction', 'time' => '2024-02-29T12:00:00.250+08:00'],
                ['title' => 'UTC fraction', 'time' => '2024-02-29T05:00:00.500Z'],
                ['title' => 'Invalid day with offset', 'time' => '2024-02-31T12:00:00+08:00'],
            ],
        ]);
        $service = new MovieTorrentCache($this->createMock(MteamApiClient::class), $this->app->make(CacheFactory::class));

        $movies = $service->moviesForCategories(['401']);

        $this->assertSame([
            'UTC fraction',
            'Offset with fraction',
            'Invalid leap day',
            'Invalid day with offset',
        ], array_column($movies, 'title'));
    }

    public function test_cached_raw_small_description_provides_a_chinese_display_title_without_refreshing(): void
    {
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => [[
                'title' => 'Rob Lowe: The Outsider',
                'raw' => ['smallDescr' => ' 罗伯・洛：小教父本色/Rob Lowe: The Outsider | 2026 | WEB-DL '],
            ]],
        ]);
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->never())->method('search');
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        $movies = $service->moviesForCategories(['401']);

        $this->assertSame('罗伯・洛：小教父本色', $movies[0]['title']);
    }

    public function test_cached_raw_small_description_without_han_characters_falls_back_to_the_torrent_title(): void
    {
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => [[
                'title' => 'Rob Lowe: The Outsider',
                'raw' => ['smallDescr' => 'Rob Lowe: The Outsider/English title | 2026 | WEB-DL'],
            ]],
        ]);
        $service = new MovieTorrentCache($this->createMock(MteamApiClient::class), $this->app->make(CacheFactory::class));

        $movies = $service->moviesForCategories(['401']);

        $this->assertSame('Rob Lowe: The Outsider', $movies[0]['title']);
    }

    public function test_cached_raw_small_description_uses_a_chinese_segment_after_a_slash_when_needed(): void
    {
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => [[
                'title' => 'The Outsider',
                'raw' => ['smallDescr' => 'The Outsider/小教父本色 | 2026 | WEB-DL'],
            ]],
        ]);
        $service = new MovieTorrentCache($this->createMock(MteamApiClient::class), $this->app->make(CacheFactory::class));

        $movies = $service->moviesForCategories(['401']);

        $this->assertSame('小教父本色', $movies[0]['title']);
    }

    public function test_homepage_aggregates_the_configured_category_caches_in_order_with_a_limit_per_category(): void
    {
        config()->set('services.mteam.media_categories', [
            'movies' => [
                'children' => [
                    'movies-sd' => ['ids' => ['401']],
                    'movies-hd' => ['ids' => ['419']],
                ],
            ],
        ]);
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => $this->torrents('SD', 13),
        ]);
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'419', [
            'torrents' => $this->torrents('HD', 13),
        ]);
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->never())
            ->method('search');
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));

        $movies = $service->movies();

        $this->assertSame([
            'SD 1', 'SD 2', 'SD 3', 'SD 4', 'SD 5', 'SD 6',
            'SD 7', 'SD 8', 'SD 9', 'SD 10', 'SD 11', 'SD 12',
            'HD 1', 'HD 2', 'HD 3', 'HD 4', 'HD 5', 'HD 6',
            'HD 7', 'HD 8', 'HD 9', 'HD 10', 'HD 11', 'HD 12',
        ], array_column($movies, 'title'));
    }

    public function test_homepage_uses_the_configured_limit_for_each_category(): void
    {
        config()->set('services.mteam.media_categories', [
            'movies' => [
                'children' => [
                    'movies-sd' => ['ids' => ['401']],
                    'movies-hd' => ['ids' => ['419']],
                ],
            ],
        ]);
        config()->set('services.mteam.homepage_category_limit', 2);
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => $this->torrents('SD', 3),
        ]);
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'419', [
            'torrents' => $this->torrents('HD', 3),
        ]);
        $service = new MovieTorrentCache($this->createMock(MteamApiClient::class), $this->app->make(CacheFactory::class));

        $movies = $service->movies();

        $this->assertSame(['SD 1', 'SD 2', 'HD 1', 'HD 2'], array_column($movies, 'title'));
    }

    public function test_homepage_reads_cached_categories_without_refreshing_or_writing_to_cache(): void
    {
        config()->set('services.mteam.media_categories', [
            'movies' => [
                'children' => [
                    'movies-sd' => ['ids' => ['401']],
                    'movies-hd' => ['ids' => ['419']],
                ],
            ],
        ]);
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->never())->method('search');
        $repository = $this->createMock(Repository::class);
        $repository->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                [MovieTorrentCache::CACHE_KEY_PREFIX.'401', [], ['torrents' => [['title' => 'SD movie']]]],
                [MovieTorrentCache::CACHE_KEY_PREFIX.'419', [], ['torrents' => [['title' => 'HD movie']]]],
            ]);
        $repository->expects($this->never())->method('put');
        $cache = $this->createMock(CacheFactory::class);
        $cache->expects($this->once())->method('store')->with('mteam_movies')->willReturn($repository);
        $service = new MovieTorrentCache($client, $cache);

        $movies = $service->movies();

        $this->assertSame(['SD movie', 'HD movie'], array_column($movies, 'title'));
    }

    public function test_homepage_sorts_each_configured_category_before_applying_its_limit(): void
    {
        config()->set('services.mteam.media_categories', [
            'movies' => [
                'children' => [
                    'movies-sd' => ['ids' => ['401']],
                    'movies-hd' => ['ids' => ['419']],
                ],
            ],
        ]);
        config()->set('services.mteam.homepage_category_limit', 2);
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => [
                ['title' => 'SD oldest', 'time' => '2026-01-01 00:00:00'],
                ['title' => 'SD newest', 'time' => '2026-03-01 00:00:00'],
                ['title' => 'SD middle', 'time' => '2026-02-01 00:00:00'],
            ],
        ]);
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'419', [
            'torrents' => [
                ['title' => 'HD oldest', 'time' => '2026-04-01T00:00:00Z'],
                ['title' => 'HD newest', 'time' => '2026-06-01T00:00:00Z'],
                ['title' => 'HD middle', 'time' => '2026-05-01T00:00:00Z'],
            ],
        ]);
        $service = new MovieTorrentCache($this->createMock(MteamApiClient::class), $this->app->make(CacheFactory::class));

        $movies = $service->movies();

        $this->assertSame(
            ['SD newest', 'SD middle', 'HD newest', 'HD middle'],
            array_column($movies, 'title'),
        );
    }

    public function test_configured_category_ids_include_all_adult_children_in_display_order(): void
    {
        $service = new MovieTorrentCache($this->createMock(MteamApiClient::class), $this->app->make(CacheFactory::class));

        $this->assertSame(
            ['419', '421', '439', '420', '401', '402', '438', '404', '435', '403', '405', '453', '423', '448', '429', '430', '432', '426', '431', '410', '424', '437', '412'],
            $service->configuredCategoryIds(),
        );
        $this->assertSame(
            ['419', '421', '439', '420', '401', '402', '438', '404', '435', '403', '405', '453', '423', '448'],
            $service->homepageCategoryIds(),
        );
    }

    public function test_adult_child_mapping_preserves_exact_labels_ids_and_distinct_duplicate_label_slugs(): void
    {
        $children = config('services.mteam.media_categories.adult.children');

        $this->assertSame([
            'adult-hd-uncensored' => ['label' => '高清无码', 'type' => 'adult', 'ids' => ['429']],
            'adult-sd-uncensored' => ['label' => '标清无码', 'type' => 'adult', 'ids' => ['430']],
            'adult-bluray-uncensored' => ['label' => '蓝光无码', 'type' => 'adult', 'ids' => ['432']],
            'adult-dvd-uncensored' => ['label' => 'DVD无码', 'type' => 'adult', 'ids' => ['426']],
            'adult-bluray-censored' => ['label' => '蓝光有码', 'type' => 'adult', 'ids' => ['431']],
            'adult-hd-censored' => ['label' => '高清有码', 'type' => 'adult', 'ids' => ['410']],
            'adult-sd-censored-424' => ['label' => '标清有码', 'type' => 'adult', 'ids' => ['424']],
            'adult-sd-censored-437' => ['label' => '标清有码', 'type' => 'adult', 'ids' => ['437']],
            'adult-animation' => ['label' => '动画', 'type' => 'adult', 'ids' => ['412']],
        ], $children);
        $this->assertTrue((bool) config('services.mteam.media_categories.adult.exclude_from_homepage'));
        $this->assertSame(['mode' => 'adult'], config('services.mteam.media_categories.adult.request'));
    }

    public function test_homepage_excludes_categories_marked_to_be_excluded_from_the_homepage(): void
    {
        config()->set('services.mteam.media_categories', [
            'movies' => [
                'children' => [
                    'movies-sd' => ['ids' => ['401']],
                ],
            ],
            'adult' => [
                'exclude_from_homepage' => true,
                'children' => [
                    'adult-bluray-censored' => ['ids' => ['431']],
                ],
            ],
        ]);
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => $this->torrents('Movie', 13),
            'refreshed_at' => '2026-11-24T11:00:00+00:00',
        ]);

        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'431', [
            'torrents' => $this->torrents('Adult', 13),
            'refreshed_at' => '2026-11-24T12:00:00+00:00',
        ]);

        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->never())->method('search');
        $service = new MovieTorrentCache($client, $this->app->make(CacheFactory::class));
        $movies = $service->movies();

        $this->assertSame(['401'], $service->homepageCategoryIds());
        $this->assertSame(['401', '431'], $service->configuredCategoryIds());
        $this->assertSame([
            'Movie 1', 'Movie 2', 'Movie 3', 'Movie 4', 'Movie 5', 'Movie 6',
            'Movie 7', 'Movie 8', 'Movie 9', 'Movie 10', 'Movie 11', 'Movie 12',
        ], array_column($movies, 'title'));
        $this->assertSame('2026-11-24T11:00:00+00:00', $service->refreshedAt());
        $this->assertSame(['Adult 1', 'Adult 2', 'Adult 3', 'Adult 4', 'Adult 5', 'Adult 6', 'Adult 7', 'Adult 8', 'Adult 9', 'Adult 10', 'Adult 11', 'Adult 12', 'Adult 13'], array_column($service->moviesForCategories(['431']), 'title'));
        $this->assertSame('2026-11-24T12:00:00+00:00', $service->refreshedAtForCategories(['431']));
    }

    public function test_category_aggregation_returns_all_cached_items_without_a_limit(): void
    {
        $cache = $this->cache();
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => $this->torrents('SD', 13),
        ]);
        $service = new MovieTorrentCache($this->createMock(MteamApiClient::class), $this->app->make(CacheFactory::class));

        $movies = $service->moviesForCategories(['401']);

        $this->assertSame($this->torrents('SD', 13), $movies);
    }

    public function test_category_refresh_uses_the_configured_cache_ttl(): void
    {
        config()->set('services.mteam.cache_ttl', 1800);

        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->once())
            ->method('search')
            ->willReturn(['data' => [['id' => 7, 'title' => 'Arrival', 'category' => '401']]]);
        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('put')
            ->with(
                MovieTorrentCache::CACHE_KEY_PREFIX.'401',
                $this->isArray(),
                1800,
            );
        $cache = $this->createMock(CacheFactory::class);
        $cache->expects($this->once())
            ->method('store')
            ->with('mteam_movies')
            ->willReturn($repository);
        $service = new MovieTorrentCache($client, $cache);

        $service->refreshCategory('401');
    }

    private function cache(): Repository
    {
        config()->set('cache.stores.mteam_movies', ['driver' => 'array']);

        return $this->app->make(CacheFactory::class)->store('mteam_movies');
    }

    /**
     * @return array<int, array{title: string}>
     */
    private function torrents(string $prefix, int $count): array
    {
        return array_map(
            fn (int $number): array => ['title' => sprintf('%s %d', $prefix, $number)],
            range(1, $count),
        );
    }
}
