<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class QbittorrentApiClient
{
    private const LOGIN_ENDPOINT = '/api/v2/auth/login';

    private const ADD_TORRENT_ENDPOINT = '/api/v2/torrents/add';

    private const TORRENTS_INFO_ENDPOINT = '/api/v2/torrents/info';

    public function __construct(
        private HttpFactory $http,
        private CacheRepository $cache,
    ) {}

    public function addTorrent(string $url, string $account = 'default', ?string $category = null): void
    {
        $configuration = $this->accountConfiguration($account);
        $session = $this->session($account, $configuration);

        try {
            $data = ['urls' => $url];

            if ($category !== null) {
                $data['category'] = $category;
            }

            $response = $this->http
                ->baseUrl($configuration['url'])
                ->connectTimeout(5)
                ->timeout(30)
                ->withHeaders([
                    'Cookie' => $session,
                    'Referer' => $configuration['url'],
                ])
                ->asMultipart()
                ->post(self::ADD_TORRENT_ENDPOINT, $data);
        } catch (\Throwable) {
            throw new RuntimeException('qBittorrent 服务暂时不可用。');
        }

        if ($response->status() === 403) {
            $this->cache->forget($this->sessionCacheKey($account, $configuration));
        }

        if (! $response->successful()) {
            throw new RuntimeException('qBittorrent 未能添加种子。');
        }
    }

    /**
     * @return array<int, array{key: string, name: string}>
     */
    public function accounts(): array
    {
        $accounts = config('services.qbittorrent.accounts', []);

        if (! is_array($accounts)) {
            return [];
        }

        $configuredAccounts = [];

        foreach ($accounts as $key => $account) {
            if (! is_string($key) || ! is_array($account) || ! is_string($account['name'] ?? null) || trim($account['name']) === '') {
                continue;
            }

            try {
                $this->accountConfiguration($key);
            } catch (RuntimeException) {
                continue;
            }

            $configuredAccounts[] = [
                'key' => $key,
                'name' => trim($account['name']),
            ];
        }

        return $configuredAccounts;
    }

    /**
     * @return array<int, array{name: string, category: string, progress: float, downloadSpeed: int, eta: int, state: string}>
     */
    public function unfinishedTorrents(string $account): array
    {
        $configuration = $this->accountConfiguration($account);
        $session = $this->session($account, $configuration);

        try {
            $response = $this->http
                ->baseUrl($configuration['url'])
                ->connectTimeout(5)
                ->timeout(30)
                ->withHeaders([
                    'Cookie' => $session,
                    'Referer' => $configuration['url'],
                ])
                ->get(self::TORRENTS_INFO_ENDPOINT);
        } catch (\Throwable) {
            throw new RuntimeException('qBittorrent 服务暂时不可用。');
        }

        if ($response->status() === 403) {
            $this->cache->forget($this->sessionCacheKey($account, $configuration));
        }

        $torrents = $response->json();

        if (! $response->successful() || ! is_array($torrents)) {
            throw new RuntimeException('qBittorrent 未能返回下载状态。');
        }

        return array_values(array_filter(array_map(function (mixed $torrent): ?array {
            if (! is_array($torrent) || ! is_numeric($torrent['progress'] ?? null) || (float) $torrent['progress'] >= 1) {
                return null;
            }

            return [
                'name' => is_string($torrent['name'] ?? null) ? $torrent['name'] : '未命名种子',
                'category' => is_string($torrent['category'] ?? null) ? $torrent['category'] : '',
                'progress' => max(0, min(1, (float) $torrent['progress'])),
                'downloadSpeed' => is_numeric($torrent['dlspeed'] ?? null) ? max(0, (int) $torrent['dlspeed']) : 0,
                'eta' => is_numeric($torrent['eta'] ?? null) ? max(0, (int) $torrent['eta']) : 0,
                'state' => is_string($torrent['state'] ?? null) ? $torrent['state'] : '',
            ];
        }, $torrents)));
    }

    /**
     * @param  array{url: string, username: string, password: string}  $configuration
     */
    private function session(string $account, array $configuration): string
    {
        $cacheKey = $this->sessionCacheKey($account, $configuration);
        $session = $this->cache->get($cacheKey);

        if (is_string($session) && $session !== '') {
            return $session;
        }

        try {
            $response = $this->http
                ->baseUrl($configuration['url'])
                ->connectTimeout(5)
                ->timeout(30)
                ->withHeader('Referer', $configuration['url'])
                ->asForm()
                ->post(self::LOGIN_ENDPOINT, [
                    'username' => $configuration['username'],
                    'password' => $configuration['password'],
                ]);
        } catch (\Throwable) {
            throw new RuntimeException('qBittorrent 服务暂时不可用。');
        }

        $session = $this->sessionCookie($this->responseCookies($response->headers()));

        if (! $response->successful() || $session === null) {
            throw new RuntimeException('qBittorrent 登录失败。');
        }

        $this->cache->put(
            $cacheKey,
            $session,
            now()->addSeconds(max(1, (int) config('services.qbittorrent.session_ttl', 300))),
        );

        return $session;
    }

    /**
     * @param  array<int, string>  $cookies
     */
    private function sessionCookie(array $cookies): ?string
    {
        foreach ($cookies as $cookie) {
            if (preg_match('/^\s*([^=;,\s]+)=([^;,\s]+)/', $cookie, $matches) === 1) {
                return $matches[1].'='.$matches[2];
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @return array<int, string>
     */
    private function responseCookies(array $headers): array
    {
        foreach ($headers as $name => $values) {
            if (strcasecmp($name, 'Set-Cookie') === 0) {
                return $values;
            }
        }

        return [];
    }

    /**
     * @return array{url: string, username: string, password: string}
     */
    private function accountConfiguration(string $account): array
    {
        $configuration = config('services.qbittorrent.accounts.'.$account);

        if (! is_array($configuration)
            || ! is_string($configuration['url'] ?? null)
            || ! is_string($configuration['username'] ?? null)
            || ! is_string($configuration['password'] ?? null)
            || $configuration['url'] === ''
            || $configuration['username'] === ''
            || $configuration['password'] === '') {
            throw new RuntimeException('qBittorrent 账号未配置。');
        }

        return [
            'url' => rtrim($configuration['url'], '/'),
            'username' => $configuration['username'],
            'password' => $configuration['password'],
        ];
    }

    /**
     * @param  array{url: string, username: string, password: string}  $configuration
     */
    private function sessionCacheKey(string $account, array $configuration): string
    {
        return 'qbittorrent.session.'.hash('sha256', implode('|', [
            $account,
            $configuration['url'],
            $configuration['username'],
            $configuration['password'],
        ]));
    }
}
