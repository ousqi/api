<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class MteamApiClient
{
    public function __construct(private HttpFactory $http) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function search(array $filters): array
    {
        $endpoint = config('services.mteam.endpoint');
        $key = config('services.mteam.key');

        if (! is_string($endpoint) || $endpoint === '' || ! is_string($key) || $key === '') {
            throw new RuntimeException('MTeam 服务未配置。');
        }

        try {
            $response = $this->http
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(30)
                ->withHeaders(['x-api-key' => $key])
                ->post(rtrim($endpoint, '/').'/api/torrent/search', array_merge([
                    'visible' => 1,
                    'pageNumber' => 1,
                    'pageSize' => 100,
                ], $filters));
        } catch (\Throwable) {
            throw new RuntimeException('MTeam 服务暂时不可用。');
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('MTeam 未返回可用的种子资料。');
        }

        return $response->json();
    }
}
