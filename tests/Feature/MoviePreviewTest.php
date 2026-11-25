<?php

namespace Tests\Feature;

use App\Services\MovieTorrentCache;
use App\Services\MteamApiClient;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MoviePreviewTest extends TestCase
{
    public function test_home_page_excludes_all_adult_caches_from_movies_hero_count_and_refresh_time(): void
    {
        config()->set('cache.stores.mteam_movies', ['driver' => 'array']);
        $cache = $this->app->make(CacheFactory::class)->store('mteam_movies');
        $homepageCategoryId = $this->homepageCategoryIds()[0];
        $homepagePoster = 'https://cache.example.test/homepage.jpg';
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.$homepageCategoryId, [
            'torrents' => [$this->torrent('Homepage movie', $homepagePoster)],
            'refreshed_at' => '2026-11-24T11:00:00+00:00',
        ]);

        foreach ($this->categoryIdsFor('adult') as $categoryId) {
            $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.$categoryId, [
                'torrents' => [$this->torrent('Adult '.$categoryId, 'https://cache.example.test/adult-'.$categoryId.'.jpg')],
                'refreshed_at' => '2026-11-24T12:00:00+00:00',
            ]);
        }

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Homepage movie')
            ->assertSee('src="'.$homepagePoster.'"', false)
            ->assertSee('已收录 <strong class="font-semibold text-white">1</strong> 部', false)
            ->assertSee('2026-11-24T11:00:00+00:00');

        foreach ($this->categoryIdsFor('adult') as $categoryId) {
            $response
                ->assertDontSee('Adult '.$categoryId)
                ->assertDontSee('https://cache.example.test/adult-'.$categoryId.'.jpg');
        }
    }

    public function test_home_page_uses_only_the_configured_homepage_category_caches(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $categoryIds = $this->homepageCategoryIds();
        $cache->expects($this->once())->method('homepageCategoryIds')->willReturn($categoryIds);
        $cache->expects($this->once())
            ->method('movies')
            ->willReturn([
                $this->torrent('Movie', 'https://cache.example.test/movie.jpg'),
                $this->torrent('Animation', null),
                $this->torrent('Game', null),
            ]);
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->with($categoryIds)
            ->willReturn('2026-11-24T12:00:00+00:00');
        $this->app->instance(MovieTorrentCache::class, $cache);

        $this->get('/')
            ->assertOk()
            ->assertSee('Movie')
            ->assertSee('Animation')
            ->assertSee('Game')
            ->assertSee('2026-11-24T12:00:00+00:00');
    }

    public function test_home_page_groups_each_categorys_newest_cached_movies_in_configured_order(): void
    {
        config()->set('services.mteam.media_categories.movies.children', [
            'movies-sd' => ['label' => '电影-标清', 'type' => 'movie', 'ids' => ['401']],
            'movies-hd' => ['label' => '电影-高清', 'type' => 'movie', 'ids' => ['419']],
        ]);
        config()->set('services.mteam.homepage_category_limit', 2);
        config()->set('cache.stores.mteam_movies', ['driver' => 'array']);
        $cache = $this->app->make(CacheFactory::class)->store('mteam_movies');
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'401', [
            'torrents' => [
                [...$this->torrent('SD oldest'), 'time' => '2026-01-01 00:00:00'],
                [...$this->torrent('SD newest'), 'time' => '2026-03-01 00:00:00'],
                [...$this->torrent('SD middle'), 'time' => '2026-02-01 00:00:00'],
            ],
        ]);
        $cache->put(MovieTorrentCache::CACHE_KEY_PREFIX.'419', [
            'torrents' => [
                [...$this->torrent('HD oldest'), 'time' => '2026-04-01T00:00:00Z'],
                [...$this->torrent('HD newest'), 'time' => '2026-06-01T00:00:00Z'],
                [...$this->torrent('HD middle'), 'time' => '2026-05-01T00:00:00Z'],
            ],
        ]);
        $client = $this->createMock(MteamApiClient::class);
        $client->expects($this->never())->method('search');
        $this->app->instance(MteamApiClient::class, $client);
        DB::shouldReceive('connection')->never();

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['SD newest', 'SD middle', 'HD newest', 'HD middle'])
            ->assertDontSee('SD oldest')
            ->assertDontSee('HD oldest');
    }

    public function test_movie_parent_page_aggregates_only_movie_child_category_caches(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $cache->expects($this->once())
            ->method('moviesForCategories')
            ->with(['419', '421', '439', '420', '401'])
            ->willReturn($this->torrents('Movie', 13));
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->with(['419', '421', '439', '420', '401'])
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $this->get('/movies')
            ->assertOk()
            ->assertSee('电影')
            ->assertSee('Movie 13');
    }

    public function test_category_page_searches_only_its_current_categories(): void
    {
        RateLimiter::clear('mteam-search:127.0.0.1');
        $cache = $this->createMock(MovieTorrentCache::class);
        $categoryIds = ['419', '421', '439', '420', '401'];
        $cache->expects($this->once())
            ->method('search')
            ->with('周星驰', $categoryIds)
            ->willReturn([$this->torrent('功夫')]);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $this->get('/movies?q=周星驰')
            ->assertOk()
            ->assertSee('SEARCH RESULTS')
            ->assertSee('“周星驰” 的搜索结果')
            ->assertSee('功夫')
            ->assertSee('id="movie-search"', false)
            ->assertSee('value="周星驰"', false)
            ->assertDontSee('data-hero-carousel', false);
    }

    public function test_category_page_limits_searches_to_one_per_minute_per_ip_address(): void
    {
        RateLimiter::clear('mteam-search:127.0.0.1');
        $cache = $this->createMock(MovieTorrentCache::class);
        $cache->expects($this->once())
            ->method('search')
            ->with('周星驰', ['419', '421', '439', '420', '401'])
            ->willReturn([$this->torrent('功夫')]);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $this->get('/movies?q=周星驰')
            ->assertOk()
            ->assertSee('功夫');

        $this->get('/movies?q=成龙')
            ->assertOk()
            ->assertSee('搜索过于频繁，请在');
    }

    public function test_home_page_renders_only_compact_primary_navigation_without_child_groups(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $cache->expects($this->once())
            ->method('homepageCategoryIds')
            ->willReturn($this->homepageCategoryIds());
        $cache->expects($this->once())
            ->method('movies')
            ->willReturn([]);
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('href="'.route('movies.category').'"', false)
            ->assertSee('href="'.route('tv-shows.category').'"', false)
            ->assertSee('href="'.route('adult.category').'"', false)
            ->assertDontSee('href="'.route('movies.child-category', ['category' => 'movies-sd']).'"', false)
            ->assertDontSee('href="'.route('tv-shows.child-category', ['category' => 'tv-sd']).'"', false)
            ->assertDontSee('href="'.route('adult.child-category', ['category' => 'adult-sd-censored-424']).'"', false)
            ->assertDontSee('data-menu-controller', false)
            ->assertDontSee('data-menu-trigger', false)
            ->assertDontSee('id="movie-search"', false)
            ->assertDontSee('All 电影')
            ->assertDontSee('All 电视剧');
    }

    public function test_adult_primary_navigation_is_discreet_when_inactive_and_visible_when_active(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $cache->expects($this->once())
            ->method('homepageCategoryIds')
            ->willReturn($this->homepageCategoryIds());
        $cache->expects($this->once())
            ->method('movies')
            ->willReturn([]);
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $inactiveAdultLink = $this->navigationLink(
            $this->get('/')->assertOk()->getContent(),
            '主要媒体导航',
            route('adult.category'),
        );

        $this->assertSame('成人', trim($inactiveAdultLink->textContent));
        $this->assertSame('', $inactiveAdultLink->getAttribute('aria-current'));
        $this->assertSame('', $inactiveAdultLink->getAttribute('title'));
        $this->assertSame('', $inactiveAdultLink->getAttribute('aria-describedby'));
        $this->assertSame('', $inactiveAdultLink->getAttribute('data-tooltip'));
        $this->assertStringContainsString('bg-[#101114]', $inactiveAdultLink->getAttribute('class'));
        $this->assertStringContainsString('text-[#101114]', $inactiveAdultLink->getAttribute('class'));
        $this->assertStringContainsString('hover:bg-[#101114]', $inactiveAdultLink->getAttribute('class'));
        $this->assertStringContainsString('hover:text-[#101114]', $inactiveAdultLink->getAttribute('class'));
        $this->assertStringNotContainsString('hover:text-white', $inactiveAdultLink->getAttribute('class'));
        $this->assertStringContainsString('focus-visible:outline-2', $inactiveAdultLink->getAttribute('class'));
        $this->assertStringContainsString('focus-visible:outline-offset-2', $inactiveAdultLink->getAttribute('class'));
        $this->assertStringContainsString('focus-visible:outline-sky-300', $inactiveAdultLink->getAttribute('class'));
        $this->assertStringNotContainsString('focus-visible:text-', $inactiveAdultLink->getAttribute('class'));

        foreach ([
            '/adult' => route('adult.category'),
            '/adult/adult-sd-censored-424' => route('adult.category'),
        ] as $path => $href) {
            $cache = $this->createMock(MovieTorrentCache::class);
            $cache->expects($this->once())
                ->method('moviesForCategories')
                ->willReturn([]);
            $cache->expects($this->once())
                ->method('refreshedAtForCategories')
                ->willReturn(null);
            $this->app->instance(MovieTorrentCache::class, $cache);

            $activeAdultLink = $this->navigationLink(
                $this->get($path)->assertOk()->getContent(),
                '主要媒体导航',
                $href,
            );

            $this->assertSame('page', $activeAdultLink->getAttribute('aria-current'));
            $this->assertStringContainsString('bg-sky-400/12', $activeAdultLink->getAttribute('class'));
            $this->assertStringContainsString('text-sky-200', $activeAdultLink->getAttribute('class'));
        }
    }

    public function test_adult_pages_require_a_password_and_keep_access_for_two_hours(): void
    {
        config()->set('services.adult.password', 'correct-password');

        $this->get('/adult')
            ->assertRedirect(route('movies.index', ['adult_auth' => 1]))
            ->assertSessionHas('adult.intended_url', '/adult');

        $this->from(route('movies.index', ['adult_auth' => 1]))
            ->post(route('adult-access.store'), [
                'password' => 'incorrect-password',
                'intended' => '/adult',
            ])
            ->assertRedirect(route('movies.index', ['adult_auth' => 1]))
            ->assertSessionHasErrors('password');

        $this->post(route('adult-access.store'), [
            'password' => 'correct-password',
            'intended' => '/adult',
        ])
            ->assertRedirect(route('adult.category'))
            ->assertSessionHas('adult.authorized_until');

        $this->withSession(['adult.authorized_until' => now()->subSecond()->getTimestamp()])
            ->get('/adult')
            ->assertRedirect(route('movies.index', ['adult_auth' => 1]));
    }

    public function test_parent_pages_render_only_their_contextual_child_navigation(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $cache->expects($this->exactly(3))
            ->method('moviesForCategories')
            ->willReturn([]);
        $cache->expects($this->exactly(3))
            ->method('refreshedAtForCategories')
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $this->get('/movies')
            ->assertOk()
            ->assertSee('href="'.route('movies.category').'"', false)
            ->assertSeeInOrder(['电影-高清', '电影-蓝光', '电影-Remux', '电影-DVD', '电影-标清'])
            ->assertSee('href="'.route('movies.child-category', ['category' => 'movies-sd']).'"', false)
            ->assertDontSee('href="'.route('tv-shows.child-category', ['category' => 'tv-sd']).'"', false)
            ->assertDontSee('href="'.route('adult.child-category', ['category' => 'adult-bluray-censored']).'"', false);

        $this->get('/tv-shows')
            ->assertOk()
            ->assertSee('href="'.route('tv-shows.category').'"', false)
            ->assertSeeInOrder(['影剧/综艺-高清', '影剧/综艺-蓝光', '教育纪录', '影剧/综艺-DVD', '影剧/综艺-标清'])
            ->assertSee('href="'.route('tv-shows.child-category', ['category' => 'tv-sd']).'"', false)
            ->assertDontSee('href="'.route('movies.child-category', ['category' => 'movies-sd']).'"', false)
            ->assertDontSee('href="'.route('adult.child-category', ['category' => 'adult-bluray-censored']).'"', false);

        $this->get('/adult')
            ->assertOk()
            ->assertSee('href="'.route('adult.category').'"', false)
            ->assertSeeInOrder(['高清无码', '标清无码', '蓝光无码', 'DVD无码', '蓝光有码', '高清有码', '标清有码', '标清有码', '动画'])
            ->assertSee('href="'.route('adult.child-category', ['category' => 'adult-sd-censored-437']).'"', false)
            ->assertDontSee('成人 高清无码')
            ->assertDontSee('href="'.route('movies.child-category', ['category' => 'movies-sd']).'"', false)
            ->assertDontSee('href="'.route('tv-shows.child-category', ['category' => 'tv-sd']).'"', false);
    }

    public function test_contextual_navigation_marks_only_the_expected_links_as_current(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $cache->expects($this->exactly(6))
            ->method('moviesForCategories')
            ->willReturn([]);
        $cache->expects($this->exactly(6))
            ->method('refreshedAtForCategories')
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        foreach ([
            ['/movies', '电影', route('movies.category'), route('movies.category')],
            ['/movies/movies-sd', '电影', route('movies.category'), route('movies.child-category', ['category' => 'movies-sd'])],
            ['/tv-shows', '电视剧', route('tv-shows.category'), route('tv-shows.category')],
            ['/tv-shows/tv-sd', '电视剧', route('tv-shows.category'), route('tv-shows.child-category', ['category' => 'tv-sd'])],
            ['/adult', '成人', route('adult.category'), route('adult.category')],
            ['/adult/adult-sd-censored-424', '成人', route('adult.category'), route('adult.child-category', ['category' => 'adult-sd-censored-424'])],
        ] as [$path, $categoryLabel, $primaryHref, $secondaryHref]) {
            $content = $this->get($path)->assertOk()->getContent();

            $this->assertNavigationHasOnlyCurrentLink($content, '主要媒体导航', $primaryHref);
            $this->assertNavigationHasOnlyCurrentLink($content, $categoryLabel.'分类', $secondaryHref);
        }
    }

    public function test_home_page_renders_at_most_ten_valid_poster_movies_in_hero_carousel(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $categoryIds = $this->homepageCategoryIds();
        $cache->expects($this->once())->method('homepageCategoryIds')->willReturn($categoryIds);
        $cache->expects($this->once())
            ->method('movies')
            ->willReturn([
                ...$this->torrents('Valid poster', 12, 'https://cache.example.test/poster-'),
                $this->torrent('Invalid poster', 'ftp://cache.example.test/invalid.jpg'),
                $this->torrent('Missing poster'),
            ]);
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->with($categoryIds)
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $content = $this->get('/')->assertOk()->getContent();
        preg_match_all('/<article class="swiper-slide[^"]*" data-hero-slide>(.*?)<\/article>/s', $content, $heroSlides);

        $this->assertSame(10, substr_count($content, 'data-hero-slide'));
        $this->assertStringContainsString('data-hero-carousel', $content);
        $this->assertStringContainsString('data-hero-previous', $content);
        $this->assertStringContainsString('data-hero-next', $content);
        $this->assertStringContainsString('data-hero-pagination', $content);
        $this->assertStringNotContainsString('data-hero-autoplay-toggle', $content);
        $this->assertStringNotContainsString('暂停自动播放', $content);
        $this->assertMatchesRegularExpression('/data-hero-fallback(?:="")?\s+hidden/', $content);
        $this->assertMatchesRegularExpression('/data-hero-fallback-content(?:="")?\s+hidden/', $content);
        $this->assertSame(10, count($heroSlides[1]));
        $this->assertStringNotContainsString('src="ftp://cache.example.test/invalid.jpg"', implode('', $heroSlides[1]));
        $this->assertStringNotContainsString('images/bg1.jpg', implode('', $heroSlides[1]));
        $this->assertStringNotContainsString('images/bg2.jpg', implode('', $heroSlides[1]));
        $this->assertMatchesRegularExpression('/<img src="[^"]+" alt="" class="[^"]*pointer-events-none[^"]*" aria-hidden="true">/', $content);
        $this->assertStringContainsString('object-cover opacity-100 sm:scale-105 sm:opacity-70 sm:blur-xl', $content);
        $this->assertStringContainsString('rgba(16,17,20,0.97),rgba(16,17,20,0.88)_38%,rgba(16,17,20,0.35)_62%,rgba(16,17,20,0.20)', $content);
        $this->assertStringContainsString('hidden h-auto w-auto max-w-full', $content);
        $this->assertStringContainsString('object-contain', $content);
        $this->assertStringContainsString('data-hero-poster onerror="this.dataset.heroPosterFailed=\'true\';"', $content);
        $this->assertMatchesRegularExpression('/<section class="[^"]*border border-white\/8[^"]*" aria-label="精选媒体">/', $content);
        $this->assertDoesNotMatchRegularExpression('/<section class="[^"]*rounded-[^"]*" aria-label="精选媒体">/', $content);
        $this->assertDoesNotMatchRegularExpression('/<img(?=[^>]*data-hero-poster)(?=[^>]*class="[^"]*rounded-)[^>]*>/', $content);
        $this->assertStringNotContainsString('object-cover object-center opacity-70', $content);
    }

    public function test_home_page_renders_a_gradient_fallback_without_swiper_markup_when_no_valid_hero_posters_exist(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $categoryIds = $this->homepageCategoryIds();
        $cache->expects($this->once())->method('homepageCategoryIds')->willReturn($categoryIds);
        $cache->expects($this->once())
            ->method('movies')
            ->willReturn([
                $this->torrent('Missing poster'),
                $this->torrent('Invalid poster', 'ftp://cache.example.test/invalid.jpg'),
            ]);
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->with($categoryIds)
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $content = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-hero-fallback', $content);
        $this->assertStringContainsString('bg-[radial-gradient(', $content);
        $this->assertStringNotContainsString('data-hero-carousel', $content);
        $this->assertStringNotContainsString('data-hero-slide', $content);
        $this->assertStringNotContainsString('data-hero-previous', $content);
        $this->assertStringNotContainsString('data-hero-next', $content);
        $this->assertStringNotContainsString('data-hero-pagination', $content);
    }

    public function test_home_page_renders_one_native_ratio_hero_poster_without_carousel_controls(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $categoryIds = $this->homepageCategoryIds();
        $poster = 'https://cache.example.test/portrait-poster.jpg';
        $cache->expects($this->once())->method('homepageCategoryIds')->willReturn($categoryIds);
        $cache->expects($this->once())
            ->method('movies')
            ->willReturn([$this->torrent('Portrait poster', $poster)]);
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->with($categoryIds)
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $content = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, 'data-hero-slide'));
        $this->assertStringContainsString('src="'.$poster.'"', $content);
        $this->assertMatchesRegularExpression('/<img src="[^"]+" alt="" class="[^"]*pointer-events-none[^"]*" aria-hidden="true">/', $content);
        $this->assertStringContainsString('hidden h-auto w-auto max-w-full', $content);
        $this->assertStringContainsString('grid min-h-[19rem] gap-3', $content);
        $this->assertStringContainsString('sm:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]', $content);
        $this->assertStringNotContainsString('data-hero-previous', $content);
        $this->assertStringNotContainsString('data-hero-next', $content);
        $this->assertStringNotContainsString('data-hero-pagination', $content);
        $this->assertStringNotContainsString('data-hero-autoplay-toggle', $content);
    }

    public function test_home_page_renders_only_valid_hero_slides_and_carousel_controls_when_fewer_than_ten_posters_exist(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $categoryIds = $this->homepageCategoryIds();
        $firstPoster = 'https://cache.example.test/first-poster.jpg';
        $secondPoster = 'https://cache.example.test/second-poster.jpg';
        $cache->expects($this->once())->method('homepageCategoryIds')->willReturn($categoryIds);
        $cache->expects($this->once())
            ->method('movies')
            ->willReturn([
                $this->torrent('First valid poster', $firstPoster),
                $this->torrent('Invalid poster', 'ftp://cache.example.test/invalid.jpg'),
                $this->torrent('Missing poster'),
                $this->torrent('Second valid poster', $secondPoster),
            ]);
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->with($categoryIds)
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $content = $this->get('/')->assertOk()->getContent();
        preg_match_all('/<article class="swiper-slide[^"]*" data-hero-slide>(.*?)<\/article>/s', $content, $heroSlides);

        $this->assertSame(2, substr_count($content, 'data-hero-slide'));
        $this->assertSame(2, count($heroSlides[1]));
        $this->assertStringContainsString('data-hero-carousel', $content);
        $this->assertStringContainsString('data-hero-previous', $content);
        $this->assertStringContainsString('data-hero-next', $content);
        $this->assertStringContainsString('data-hero-pagination', $content);
        $this->assertStringContainsString('src="'.$firstPoster.'"', $content);
        $this->assertStringContainsString('src="'.$secondPoster.'"', $content);
        $this->assertStringNotContainsString('src="ftp://cache.example.test/invalid.jpg"', implode('', $heroSlides[1]));
        $this->assertMatchesRegularExpression('/data-hero-fallback(?:="")?\s+hidden/', $content);
        $this->assertMatchesRegularExpression('/data-hero-fallback-content(?:="")?\s+hidden/', $content);
    }

    public function test_child_category_page_reads_only_its_own_cache(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $cache->expects($this->once())
            ->method('moviesForCategories')
            ->with(['401'])
            ->willReturn([$this->torrent('Standard definition')]);
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->with(['401'])
            ->willReturn('2026-11-24T12:00:00+00:00');
        $this->app->instance(MovieTorrentCache::class, $cache);

        $this->get('/movies/movies-sd')
            ->assertOk()
            ->assertSee('电影-标清')
            ->assertSee('Standard definition')
            ->assertSee('2026-11-24T12:00:00+00:00')
            ->assertSee('href="'.route('movies.category').'"', false)
            ->assertSee('href="'.route('movies.child-category', ['category' => 'movies-remux']).'"', false)
            ->assertDontSee('href="'.route('tv-shows.child-category', ['category' => 'tv-sd']).'"', false)
            ->assertDontSee('href="'.route('adult.child-category', ['category' => 'adult-bluray-censored']).'"', false);
    }

    public function test_poster_cards_open_the_matching_local_detail_dialog(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $categoryIds = $this->homepageCategoryIds();
        $cache->expects($this->once())->method('homepageCategoryIds')->willReturn($categoryIds);
        $cache->expects($this->once())
            ->method('movies')
            ->willReturn([[
                ...$this->torrent('罗伯・洛：小教父本色', null, '完整影片简介 <script>alert("xss")</script>'),
                'id' => '1298754',
                'raw' => ['category' => '419'],
            ]]);
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->with($categoryIds)
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $content = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Home · 小甜甜影院</title>', $content);
        $this->assertStringContainsString('>小甜甜影院</span>', $content);
        $this->assertStringContainsString('完整影片简介', $content);
        $this->assertStringContainsString('&lt;script&gt;alert', $content);
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $content);
        $this->assertStringContainsString('data-torrent-detail-trigger', $content);
        $this->assertStringContainsString('data-torrent-detail-url="'.route('movies.detail', ['categoryId' => '419', 'torrent' => '1298754']).'"', $content);
        $this->assertStringContainsString('id="torrent-detail-dialog"', $content);
        $this->assertStringContainsString('data-torrent-detail-loading', $content);
        $this->assertStringContainsString('data-torrent-detail-description', $content);
        $this->assertStringNotContainsString('https://kp.m-team.cc/detail/1298754', $content);
        $this->assertStringContainsString('loading="lazy"', $content);
        $this->assertStringContainsString('decoding="async"', $content);
        $this->assertStringContainsString(
            'class="group/card relative block cursor-pointer overflow-hidden border border-white/8',
            $content,
        );
        $this->assertStringContainsString(
            'class="aspect-[2/3] overflow-hidden bg-zinc-800"',
            $content,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<div class="aspect-\[2\/3\] overflow-hidden[^"]*\brounded(?:-[^\s"]*)?[^"]*">/',
            $content,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<img(?=[^>]*data-fallback-poster)(?=[^>]*class="[^"]*\brounded(?:-[^\s"]*)?")[^>]*>/',
            $content,
        );
        $this->assertStringNotContainsString('data-torrent-detail-media-info-section', $content);
    }

    public function test_poster_cards_use_a_preselected_local_fallback_for_missing_or_invalid_posters_and_remote_load_errors(): void
    {
        $cache = $this->createMock(MovieTorrentCache::class);
        $categoryIds = $this->homepageCategoryIds();
        $remotePoster = 'https://cache.example.test/remote-poster.jpg';
        $cache->expects($this->once())->method('homepageCategoryIds')->willReturn($categoryIds);
        $cache->expects($this->once())
            ->method('movies')
            ->willReturn([
                $this->torrent('Missing poster'),
                $this->torrent('Invalid poster', 'ftp://cache.example.test/invalid-poster.jpg'),
                $this->torrent('Remote poster', $remotePoster),
            ]);
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->with($categoryIds)
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $content = $this->get('/')->assertOk()->getContent();
        $fallbackPattern = preg_quote(asset('images/bg'), '/').'[12]\.jpg';
        preg_match_all(
            '/<img(?=[^>]*alt="(?:Missing poster|Invalid poster) 海报")[^>]*>/',
            $content,
            $fallbackPosterCards,
        );

        $this->assertCount(2, $fallbackPosterCards[0]);
        foreach ($fallbackPosterCards[0] as $fallbackPosterCard) {
            $this->assertMatchesRegularExpression(
                '/src="('.$fallbackPattern.')"[^>]*data-fallback-poster="\1"/',
                $fallbackPosterCard,
            );
        }
        $this->assertStringNotContainsString('src="ftp://cache.example.test/invalid-poster.jpg"', $content);
        $this->assertStringContainsString('src="'.$remotePoster.'"', $content);
        $this->assertMatchesRegularExpression(
            '/data-fallback-poster="'.$fallbackPattern.'"[^>]*onerror="this\.onerror=null;this\.src=this\.dataset\.fallbackPoster;"/',
            $content,
        );
    }

    public function test_animation_and_games_parent_and_child_pages_read_only_their_configured_caches(): void
    {
        $requestedCategoryIds = [];
        $cache = $this->createMock(MovieTorrentCache::class);
        $cache->expects($this->exactly(4))
            ->method('moviesForCategories')
            ->willReturnCallback(function (array $categoryIds) use (&$requestedCategoryIds): array {
                $requestedCategoryIds[] = $categoryIds;

                return [$this->torrent('Category '.implode('-', $categoryIds))];
            });
        $cache->expects($this->exactly(4))
            ->method('refreshedAtForCategories')
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $this->get('/animation')
            ->assertOk()
            ->assertSee('Category 405-453')
            ->assertSee('href="'.route('animation.child-category', ['category' => 'animation-anime']).'"', false)
            ->assertSee('href="'.route('animation.child-category', ['category' => 'animation-bluray']).'"', false)
            ->assertDontSee('href="'.route('movies.child-category', ['category' => 'movies-sd']).'"', false)
            ->assertDontSee('href="'.route('tv-shows.child-category', ['category' => 'tv-sd']).'"', false);
        $this->get('/animation/animation-bluray')
            ->assertOk()
            ->assertSee('Category 453')
            ->assertDontSee('Category 405-453');
        $this->get('/games')
            ->assertOk()
            ->assertSee('Category 423-448')
            ->assertSee('href="'.route('games.child-category', ['category' => 'games-pc']).'"', false)
            ->assertSee('href="'.route('games.child-category', ['category' => 'games-tv']).'"', false)
            ->assertDontSee('href="'.route('movies.child-category', ['category' => 'movies-sd']).'"', false)
            ->assertDontSee('href="'.route('adult.child-category', ['category' => 'adult-bluray-censored']).'"', false);
        $this->get('/games/games-tv')
            ->assertOk()
            ->assertSee('Category 448')
            ->assertDontSee('Category 423-448');

        $this->assertSame([['405', '453'], ['453'], ['423', '448'], ['448']], $requestedCategoryIds);
    }

    public function test_adult_parent_page_aggregates_only_its_configured_child_category_caches(): void
    {
        $adultCategoryIds = $this->categoryIdsFor('adult');
        $cache = $this->createMock(MovieTorrentCache::class);
        $cache->expects($this->once())
            ->method('moviesForCategories')
            ->with($adultCategoryIds)
            ->willReturn([$this->torrent('Adult')]);
        $cache->expects($this->once())
            ->method('refreshedAtForCategories')
            ->with($adultCategoryIds)
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $this->get('/adult')
            ->assertOk()
            ->assertSee('成人')
            ->assertSee('Adult');
    }

    public function test_duplicate_adult_child_labels_have_distinct_routes_and_read_only_their_own_caches(): void
    {
        $requestedCategoryIds = [];
        $cache = $this->createMock(MovieTorrentCache::class);
        $cache->expects($this->exactly(2))
            ->method('moviesForCategories')
            ->willReturnCallback(function (array $categoryIds) use (&$requestedCategoryIds): array {
                $requestedCategoryIds[] = $categoryIds;

                return [$this->torrent('Adult '.$categoryIds[0])];
            });
        $cache->expects($this->exactly(2))
            ->method('refreshedAtForCategories')
            ->willReturn(null);
        $this->app->instance(MovieTorrentCache::class, $cache);

        $this->get('/adult/adult-sd-censored-424')->assertOk()->assertSee('Adult 424');
        $this->get('/adult/adult-sd-censored-437')->assertOk()->assertSee('Adult 437');
        $this->get('/adult/adult-sd-censored')->assertNotFound();

        $this->assertSame('/adult/adult-sd-censored-424', route('adult.child-category', ['category' => 'adult-sd-censored-424'], false));
        $this->assertSame('/adult/adult-sd-censored-437', route('adult.child-category', ['category' => 'adult-sd-censored-437'], false));
        $this->assertSame([['424'], ['437']], $requestedCategoryIds);
    }

    /**
     * @return array<int, string>
     */
    private function homepageCategoryIds(): array
    {
        return $this->categoryIdsMatching(fn (array $definition): bool => ($definition['exclude_from_homepage'] ?? false) !== true);
    }

    /**
     * @return array<int, string>
     */
    private function configuredCategoryIds(): array
    {
        return $this->categoryIdsMatching(fn (): bool => true);
    }

    /**
     * @return array<int, string>
     */
    private function categoryIdsFor(string $categoryKey): array
    {
        $definition = config('services.mteam.media_categories.'.$categoryKey, []);

        return $this->categoryIdsFromDefinition($definition);
    }

    /**
     * @param  callable(array<string, mixed>): bool  $shouldInclude
     * @return array<int, string>
     */
    private function categoryIdsMatching(callable $shouldInclude): array
    {
        $categoryIds = [];

        foreach (config('services.mteam.media_categories') as $definition) {
            if (is_array($definition) && $shouldInclude($definition)) {
                $categoryIds = [...$categoryIds, ...$this->categoryIdsFromDefinition($definition)];
            }
        }

        return $categoryIds;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    private function categoryIdsFromDefinition(array $definition): array
    {
        $children = is_array($definition['children'] ?? null) ? $definition['children'] : [$definition];
        $categoryIds = [];

        foreach ($children as $child) {
            if (is_array($child) && is_array($child['ids'] ?? null)) {
                $categoryIds = [...$categoryIds, ...$child['ids']];
            }
        }

        return $categoryIds;
    }

    /**
     * @return array<string, mixed>
     */
    private function torrent(string $title, ?string $poster = null, ?string $description = null): array
    {
        return [
            'title' => $title,
            'poster' => $poster,
            'time' => null,
            'description' => $description,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function torrents(string $prefix, int $count, ?string $posterPrefix = null): array
    {
        return array_map(
            fn (int $number): array => $this->torrent(
                sprintf('%s %d', $prefix, $number),
                $posterPrefix === null ? null : sprintf('%s%d.jpg', $posterPrefix, $number),
            ),
            range(1, $count),
        );
    }

    private function assertNavigationHasOnlyCurrentLink(string $content, string $navigationLabel, string $href): void
    {
        $document = new \DOMDocument;
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        $xpath = new \DOMXPath($document);
        $links = $xpath->query(sprintf(
            '//nav[@aria-label="%s"]//a[@aria-current]',
            $navigationLabel,
        ));

        $this->assertSame(1, $links->length);
        $this->assertSame($href, $links->item(0)?->getAttribute('href'));
        $this->assertSame('page', $links->item(0)?->getAttribute('aria-current'));
    }

    private function navigationLink(string $content, string $navigationLabel, string $href): \DOMElement
    {
        $document = new \DOMDocument;
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document->loadHTML($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        $xpath = new \DOMXPath($document);
        $link = $xpath->query(sprintf(
            '//nav[@aria-label="%s"]//a[@href="%s"]',
            $navigationLabel,
            $href,
        ));

        $this->assertSame(1, $link->length);

        return $link->item(0);
    }
}
