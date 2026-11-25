<?php

namespace App\Http\Controllers;

use App\Services\MovieTorrentCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use RuntimeException;

class MovieController extends Controller
{
    public function index(MovieTorrentCache $movieTorrents): View
    {
        return $this->render(
            $movieTorrents,
            null,
            $movieTorrents->movies(),
            $movieTorrents->homepageCategoryIds(),
        );
    }

    public function category(MovieTorrentCache $movieTorrents, Request $request, string $category): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $searchTerm = trim((string) ($validated['q'] ?? ''));

        if ($searchTerm === '') {
            return $this->render($movieTorrents, $category);
        }

        $categoryDetails = $this->categoryDetails($category);
        $rateLimitKey = $this->searchRateLimitKey($request);

        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return $this->render(
                $movieTorrents,
                $category,
                [],
                $categoryDetails['ids'],
                $searchTerm,
                sprintf('搜索过于频繁，请在 %d 秒后重试。', RateLimiter::availableIn($rateLimitKey)),
            );
        }

        RateLimiter::hit($rateLimitKey, 60);

        try {
            $movies = $movieTorrents->search($searchTerm, $categoryDetails['ids']);
            $searchError = null;
        } catch (RuntimeException $exception) {
            $movies = [];
            $searchError = $exception->getMessage();
        }

        return $this->render(
            $movieTorrents,
            $category,
            $movies,
            $categoryDetails['ids'],
            $searchTerm,
            $searchError,
        );
    }

    private function searchRateLimitKey(Request $request): string
    {
        return 'mteam-search:'.$request->ip();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $movies
     */
    private function render(
        MovieTorrentCache $movieTorrents,
        ?string $category,
        ?array $movies = null,
        ?array $categoryIds = null,
        ?string $searchTerm = null,
        ?string $searchError = null,
    ): View
    {
        $categoryDetails = $category === null ? null : $this->categoryDetails($category);
        $categoryIds ??= $categoryDetails['ids'] ?? $movieTorrents->configuredCategoryIds();
        $movies ??= $movieTorrents->moviesForCategories($categoryIds);

        return view('movies.index', [
            'movies' => $this->cardMovies($movies),
            'refreshedAt' => $searchTerm === null ? $movieTorrents->refreshedAtForCategories($categoryIds) : null,
            'heroMovies' => $searchTerm === null ? $this->heroMovies($movies) : [],
            'categoryDetails' => $categoryDetails,
            'mediaCategories' => $this->mediaCategories(),
            'searchTerm' => $searchTerm,
            'searchError' => $searchError,
            'adultAuthorized' => (int) session('adult.authorized_until', 0) > now()->getTimestamp(),
            'adultAuthorizationRequired' => request()->boolean('adult_auth'),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $movies
     * @return array<int, array<string, mixed>>
     */
    private function heroMovies(array $movies): array
    {
        $heroMovies = array_values(array_filter(
            $movies,
            fn (mixed $movie): bool => is_array($movie)
                && is_string($movie['poster'] ?? null)
                && $this->isValidPosterUrl($movie['poster']),
        ));

        shuffle($heroMovies);

        return array_slice($heroMovies, 0, 10);
    }

    /**
     * @param  array<int, array<string, mixed>>  $movies
     * @return array<int, array<string, mixed>>
     */
    private function cardMovies(array $movies): array
    {
        return array_map(function (array $movie): array {
            $fallbackPoster = asset(sprintf('images/bg%d.jpg', random_int(1, 2)));
            $poster = $movie['poster'] ?? null;

            $movie['cardPoster'] = is_string($poster) && $this->isValidPosterUrl($poster)
                ? $poster
                : $fallbackPoster;
            $movie['fallbackPoster'] = $fallbackPoster;

            return $movie;
        }, $movies);
    }

    /**
     * @return array{key: string, parent: ?string, label: string, type: string, ids: array<int, string>}
     */
    private function categoryDetails(string $category): array
    {
        $categories = $this->mediaCategories();

        if (isset($categories[$category])) {
            $definition = $categories[$category];

            return [
                'key' => $category,
                'parent' => null,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'ids' => $this->categoryIds($definition),
            ];
        }

        foreach ($categories as $parentKey => $definition) {
            $child = $definition['children'][$category] ?? null;

            if (is_array($child)) {
                return [
                    'key' => $category,
                    'parent' => $parentKey,
                    'label' => $child['label'],
                    'type' => $child['type'],
                    'ids' => $this->categoryIds($child),
                ];
            }
        }

        abort(404);
    }

    /**
     * @param  array{ids?: mixed, children?: mixed}  $definition
     * @return array<int, string>
     */
    private function categoryIds(array $definition): array
    {
        $ids = is_array($definition['ids'] ?? null) ? $definition['ids'] : [];

        if (is_array($definition['children'] ?? null)) {
            foreach ($definition['children'] as $child) {
                if (is_array($child)) {
                    $ids = [...$ids, ...(is_array($child['ids'] ?? null) ? $child['ids'] : [])];
                }
            }
        }

        return array_values(array_unique(array_filter(
            array_map(
                fn (mixed $categoryId): string => is_string($categoryId) || is_int($categoryId)
                    ? trim((string) $categoryId)
                    : '',
                $ids,
            ),
            fn (string $categoryId): bool => $categoryId !== '',
        )));
    }

    /**
     * @return array<string, array{label: string, type: string, ids?: array<int, string>, children?: array<string, array{label: string, type: string, ids: array<int, string>}>}>
     */
    private function mediaCategories(): array
    {
        $categories = config('services.mteam.media_categories', []);

        return is_array($categories) ? $categories : [];
    }

    private function isValidPosterUrl(?string $poster): bool
    {
        if ($poster === null || filter_var($poster, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($poster, PHP_URL_SCHEME);
        $host = parse_url($poster, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true)
            && is_string($host)
            && $host !== '';
    }
}
