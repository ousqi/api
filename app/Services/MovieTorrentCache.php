<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MovieTorrentCache
{
    public const CACHE_KEY_PREFIX = 'mteam.movies.category.';

    /**
     * @var array<int, string>
     */
    private const POSTER_KEYS = [
        'poster',
        'posterUrl',
        'poster_url',
        'image',
        'imageUrl',
        'image_url',
        'cover',
        'coverUrl',
        'cover_url',
    ];

    /**
     * @var array<int, string>
     */
    private const POSTER_CONTAINER_KEYS = [
        'imdb',
        'douban',
        'mediaInfo',
        'media_info',
    ];

    /**
     * @var array<int, string>
     */
    private const IMAGE_URL_KEYS = [
        'url',
        'src',
        'original',
        'large',
        'medium',
        'default',
    ];

    public function __construct(
        private MteamApiClient $client,
        private CacheFactory $cacheFactory,
    ) {}

    private ?Repository $cache = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function movies(): array
    {
        return $this->moviesForCategories(
            $this->homepageCategoryIds(),
            max(1, (int) config('services.mteam.homepage_category_limit', 12)),
        );
    }

    /**
     * @param  array<int, string>  $categoryIds
     * @return array<int, array<string, mixed>>
     */
    public function moviesForCategories(array $categoryIds, ?int $perCategoryLimit = null): array
    {
        return array_values(array_merge([], ...array_map(
            fn (string $categoryId): array => $perCategoryLimit === null
                ? $this->moviesForCategory($categoryId)
                : array_slice($this->moviesForCategory($categoryId), 0, $perCategoryLimit),
            $this->configuredIdsFrom($categoryIds),
        )));
    }

    /**
     * @param  array<int, string>  $categoryIds
     * @return array<int, array<string, mixed>>
     */
    public function search(string $keyword, array $categoryIds): array
    {
        $categoryIds = $this->configuredIdsFrom($categoryIds);
        $keyword = trim($keyword);

        if ($keyword === '' || $categoryIds === []) {
            return [];
        }

        $response = $this->client->search([
            'pageSize' => max(1, min(100, (int) config('services.mteam.page_size', 100))),
            'keyword' => $keyword,
            'categories' => $categoryIds,
            ...$this->searchFiltersForCategories($categoryIds),
        ]);
        $items = array_values(array_filter(
            $this->extractItems($response),
            fn (array $item): bool => array_any(
                $categoryIds,
                fn (string $categoryId): bool => $this->itemMatchesCategory($item, $categoryId),
            ),
        ));

        return array_values(array_filter(
            array_map(fn (array $item): ?array => $this->normalize($item), $items),
        ));
    }

    public function refreshedAt(): ?string
    {
        return $this->refreshedAtForCategories($this->homepageCategoryIds());
    }

    /**
     * @param  array<int, string>  $categoryIds
     */
    public function refreshedAtForCategories(array $categoryIds): ?string
    {
        $refreshedAt = array_filter(array_map(
            fn (string $categoryId): ?string => $this->refreshedAtForCategory($categoryId),
            $this->configuredIdsFrom($categoryIds),
        ));

        return $refreshedAt === [] ? null : max($refreshedAt);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function refreshCategory(string $categoryId): array
    {
        if (! $this->isConfiguredCategoryId($categoryId)) {
            throw new InvalidArgumentException('分类 ID 无效或未在 MTeam 媒体分类中配置。');
        }

        $pageSize = max(1, min(100, (int) config('services.mteam.page_size', 100)));
        $response = $this->client->search([
            'pageSize' => $pageSize,
            'categories' => [$categoryId],
            ...$this->requestFiltersForCategory($categoryId),
        ]);
        $items = array_values(array_filter(
            $this->extractItems($response),
            fn (array $item): bool => $this->itemMatchesCategory($item, $categoryId),
        ));
        $torrents = array_values(array_filter(
            array_map(fn (array $item): ?array => $this->normalize($item), $items),
        ));

        if ($torrents === []) {
            throw new RuntimeException('MTeam 未返回请求分类的可展示种子。');
        }

        $this->cache()->put($this->cacheKey($categoryId), [
            'torrents' => $torrents,
            'refreshed_at' => now()->toAtomString(),
        ], max(60, (int) config('services.mteam.cache_ttl')));

        return $torrents;
    }

    public function isConfiguredCategoryId(string $categoryId): bool
    {
        return ctype_digit($categoryId) && in_array($categoryId, $this->configuredCategoryIds(), true);
    }

    /**
     * @return array<int, string>
     */
    public function configuredCategoryIds(): array
    {
        return $this->categoryIdsFromDefinitions();
    }

    /**
     * @return array<int, string>
     */
    public function homepageCategoryIds(): array
    {
        return $this->categoryIdsFromDefinitions(true);
    }

    /**
     * @return array<int, string>
     */
    private function categoryIdsFromDefinitions(bool $excludeFromHomepage = false): array
    {
        $categories = config('services.mteam.media_categories', []);

        if (! is_array($categories)) {
            return [];
        }

        $ids = [];

        foreach ($categories as $category) {
            if (! is_array($category)) {
                continue;
            }

            if ($excludeFromHomepage && ($category['exclude_from_homepage'] ?? false) === true) {
                continue;
            }

            $definitions = is_array($category['children'] ?? null)
                ? $category['children']
                : [$category];

            foreach ($definitions as $definition) {
                if (is_array($definition)) {
                    $ids = [...$ids, ...$this->configuredIdsFrom($definition['ids'] ?? [])];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function moviesForCategory(string $categoryId): array
    {
        $cached = $this->cache()->get($this->cacheKey($categoryId), []);

        if (! is_array($cached) || ! is_array($cached['torrents'] ?? null)) {
            return [];
        }

        return array_map(
            fn (mixed $torrent): mixed => is_array($torrent) ? $this->withDisplayTitle($torrent) : $torrent,
            $this->sortTorrentsByPublishTime($cached['torrents']),
        );
    }

    /**
     * @param  array<int, mixed>  $torrents
     * @return array<int, mixed>
     */
    private function sortTorrentsByPublishTime(array $torrents): array
    {
        $indexedTorrents = array_map(
            fn (mixed $torrent, int $index): array => [
                'torrent' => $torrent,
                'index' => $index,
                'publishedAt' => is_array($torrent) ? $this->publishedAtTimestamp($torrent['time'] ?? null) : null,
            ],
            array_values($torrents),
            array_keys(array_values($torrents)),
        );

        usort($indexedTorrents, function (array $left, array $right): int {
            if ($left['publishedAt'] === null && $right['publishedAt'] !== null) {
                return 1;
            }

            if ($left['publishedAt'] !== null && $right['publishedAt'] === null) {
                return -1;
            }

            if ($left['publishedAt'] !== $right['publishedAt']) {
                return $right['publishedAt'] <=> $left['publishedAt'];
            }

            return $left['index'] <=> $right['index'];
        });

        return array_column($indexedTorrents, 'torrent');
    }

    private function publishedAtTimestamp(mixed $time): ?int
    {
        if (! is_string($time)) {
            return null;
        }

        $time = trim($time);

        if ($time === '') {
            return null;
        }

        if (preg_match('/^-?\d+(?:\.\d+)?$/', $time) === 1) {
            $timestamp = (float) $time;

            return abs($timestamp) >= 100_000_000_000
                ? (int) round($timestamp)
                : (int) round($timestamp * 1000);
        }

        if (preg_match('/^(?<date>\d{4}-\d{2}-\d{2})(?:(?<separator>T| )(?<hour>\d{2}):(?<minute>\d{2})(?::(?<second>\d{2})(?<fraction>\.\d{1,6})?)?(?<offset>Z|[+-]\d{2}:?\d{2})?)?$/', $time, $matches) !== 1) {
            return null;
        }

        $date = $matches['date'];

        if (($matches['separator'] ?? '') === '') {
            return $this->strictTimestamp($date, '!Y-m-d', 'Y-m-d');
        }

        $fraction = str_pad(substr($matches['fraction'] ?? '', 1), 6, '0');
        $normalized = sprintf(
            '%s %s:%s:%s.%s',
            $date,
            $matches['hour'],
            $matches['minute'],
            $matches['second'] !== '' ? $matches['second'] : '00',
            $fraction,
        );
        $offset = $matches['offset'] ?? '';

        if ($offset !== '') {
            $offset = $offset === 'Z'
                ? '+00:00'
                : (str_contains($offset, ':') ? $offset : substr($offset, 0, 3).':'.substr($offset, 3));

            return $this->strictTimestamp($normalized.$offset, '!Y-m-d H:i:s.uP', 'Y-m-d H:i:s.uP');
        }

        return $this->strictTimestamp(
            $normalized,
            '!Y-m-d H:i:s.u',
            'Y-m-d H:i:s.u',
            new DateTimeZone(date_default_timezone_get()),
        );
    }

    private function strictTimestamp(
        string $value,
        string $format,
        string $roundTripFormat,
        ?DateTimeZone $timezone = null,
    ): ?int {
        try {
            $publishedAt = DateTimeImmutable::createFromFormat($format, $value, $timezone);

            if ($publishedAt === false || $publishedAt->format($roundTripFormat) !== $value) {
                return null;
            }

            return ($publishedAt->getTimestamp() * 1000) + (int) $publishedAt->format('v');
        } catch (Throwable) {
            return null;
        }
    }

    private function refreshedAtForCategory(string $categoryId): ?string
    {
        $cached = $this->cache()->get($this->cacheKey($categoryId), []);
        $refreshedAt = is_array($cached) ? $cached['refreshed_at'] ?? null : null;

        return is_string($refreshedAt) && trim($refreshedAt) !== '' ? trim($refreshedAt) : null;
    }

    private function cacheKey(string $categoryId): string
    {
        return self::CACHE_KEY_PREFIX.$categoryId;
    }

    private function cache(): Repository
    {
        return $this->cache ??= $this->cacheFactory->store('mteam_movies');
    }

    /**
     * @return array{mode?: string}
     */
    private function requestFiltersForCategory(string $categoryId): array
    {
        $categories = config('services.mteam.media_categories', []);

        if (! is_array($categories)) {
            return [];
        }

        foreach ($categories as $category) {
            if (! is_array($category)) {
                continue;
            }

            $definitions = is_array($category['children'] ?? null)
                ? $category['children']
                : [$category];

            foreach ($definitions as $definition) {
                if (is_array($definition) && in_array($categoryId, $this->configuredIdsFrom($definition['ids'] ?? []), true)) {
                    $mode = is_array($category['request'] ?? null)
                        ? $category['request']['mode'] ?? null
                        : null;

                    return is_string($mode) && $mode !== '' ? ['mode' => $mode] : [];
                }
            }
        }

        return [];
    }

    /**
     * @param  array<int, string>  $categoryIds
     * @return array{mode?: string}
     */
    private function searchFiltersForCategories(array $categoryIds): array
    {
        $categories = config('services.mteam.media_categories', []);

        if (! is_array($categories)) {
            return [];
        }

        foreach ($categories as $category) {
            if (! is_array($category)) {
                continue;
            }

            $definitions = is_array($category['children'] ?? null)
                ? $category['children']
                : [$category];
            $configuredIds = [];

            foreach ($definitions as $definition) {
                if (is_array($definition)) {
                    $configuredIds = [...$configuredIds, ...$this->configuredIdsFrom($definition['ids'] ?? [])];
                }
            }

            if (array_intersect($categoryIds, $configuredIds) === []) {
                continue;
            }

            $mode = is_array($category['search'] ?? null)
                ? $category['search']['mode'] ?? null
                : null;

            return is_string($mode) && $mode !== '' ? ['mode' => $mode] : [];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function configuredIdsFrom(mixed $categoryIds): array
    {
        $categoryIds = is_array($categoryIds) ? $categoryIds : [$categoryIds];

        return array_values(array_unique(array_filter(
            array_map(
                fn (mixed $categoryId): string => is_string($categoryId) || is_int($categoryId)
                    ? trim((string) $categoryId)
                    : '',
                $categoryIds,
            ),
            fn (string $categoryId): bool => ctype_digit($categoryId),
        )));
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(array $response): array
    {
        foreach (['data', 'items', 'list', 'torrents'] as $key) {
            $value = $response[$key] ?? null;

            if (is_array($value) && array_is_list($value)) {
                return array_values(array_filter($value, 'is_array'));
            }

            if (is_array($value)) {
                $items = $this->extractItems($value);

                if ($items !== []) {
                    return $items;
                }
            }
        }

        return array_is_list($response) ? array_values(array_filter($response, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemMatchesCategory(array $item, string $categoryId): bool
    {
        $category = $item['category'] ?? null;

        return (is_int($category) || is_string($category))
            && (string) $category === $categoryId;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function normalize(array $item): ?array
    {
        $title = $this->firstString($item, ['title', 'name', 'torrentName']);

        if ($title === null) {
            return null;
        }

        return [
            'id' => $this->firstString($item, ['id', 'torrentId']),
            'title' => $title,
            'poster' => $this->posterUrl($item),
            'time' => $this->firstString($item, ['createdDate', 'createdAt', 'createTime', 'publishTime', 'time']),
            'description' => $this->firstString($item, ['smallDescr', 'description', 'descr', 'subtitle']),
            'raw' => $item,
        ];
    }

    /**
     * 在 MTeam 已知的媒體欄位內尋找海報，避免將其他任意網址當成圖片。
     *
     * @param  array<string, mixed>  $item
     */
    private function posterUrl(array $item): ?string
    {
        foreach (self::POSTER_KEYS as $key) {
            $poster = $this->imageUrl($item[$key] ?? null);

            if ($poster !== null) {
                return $poster;
            }
        }

        $poster = $this->imageListPosterUrl($item['imageList'] ?? null);

        if ($poster !== null) {
            return $poster;
        }

        foreach (self::POSTER_CONTAINER_KEYS as $key) {
            $container = $item[$key] ?? null;

            if (! is_array($container)) {
                continue;
            }

            $poster = $this->findPosterUrl($container);

            if ($poster !== null) {
                return $poster;
            }
        }

        return null;
    }

    /**
     * MTeam 的 imageList 依優先順序排列；只接受列表內直接提供的絕對 HTTP(S) 圖片網址。
     */
    private function imageListPosterUrl(mixed $value): ?string
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return null;
        }

        foreach ($value as $url) {
            if (is_string($url) && $this->isPosterUrl($url)) {
                return trim($url);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findPosterUrl(array $payload, int $depth = 0): ?string
    {
        if ($depth > 5) {
            return null;
        }

        foreach (self::POSTER_KEYS as $key) {
            $poster = $this->imageUrl($payload[$key] ?? null);

            if ($poster !== null) {
                return $poster;
            }

            if (is_array($payload[$key] ?? null)) {
                $poster = $this->findPosterUrl($payload[$key], $depth + 1);

                if ($poster !== null) {
                    return $poster;
                }
            }
        }

        return null;
    }

    private function imageUrl(mixed $value): ?string
    {
        if (is_string($value)) {
            return $this->isPosterUrl($value) ? trim($value) : null;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach (self::IMAGE_URL_KEYS as $key) {
            $url = $value[$key] ?? null;

            if (is_string($url) && $this->isPosterUrl($url)) {
                return trim($url);
            }
        }

        return null;
    }

    private function isPosterUrl(string $url): bool
    {
        $url = trim($url);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true)
            && is_string($host)
            && $host !== '';
    }

    /**
     * 從既有快取的原始簡介提取中文名稱，避免重新抓取資料或翻譯標題。
     *
     * @param  array<string, mixed>  $torrent
     * @return array<string, mixed>
     */
    private function withDisplayTitle(array $torrent): array
    {
        $raw = $torrent['raw'] ?? null;
        $smallDescr = is_array($raw) && is_string($raw['smallDescr'] ?? null)
            ? trim($raw['smallDescr'])
            : '';

        if ($smallDescr === '') {
            return $torrent;
        }

        $leadingSegment = trim(explode('|', $smallDescr, 2)[0]);
        $titleSegments = str_contains($leadingSegment, '/')
            ? explode('/', $leadingSegment)
            : [$leadingSegment];

        foreach ($titleSegments as $segment) {
            $displayTitle = trim($segment);

            if ($displayTitle !== '' && preg_match('/\p{Han}/u', $displayTitle) === 1) {
                $torrent['title'] = $displayTitle;

                return $torrent;
            }
        }

        return $torrent;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $keys
     */
    private function firstString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
