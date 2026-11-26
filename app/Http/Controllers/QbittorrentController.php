<?php

namespace App\Http\Controllers;

use App\Services\MteamApiClient;
use App\Services\QbittorrentApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class QbittorrentController extends Controller
{
    private const DOWNLOAD_AUTH_SESSION_KEY = 'qbittorrent.download_authorized_until';

    public function authorizeDownload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);
        $password = config('services.qbittorrent.download_auth.password');

        if (! is_string($password) || $password === '' || ! hash_equals($password, $data['password'])) {
            return response()->json(['message' => '下载授权密码不正确。'], 422);
        }

        $authorizedUntil = now()->addSeconds((int) config('services.qbittorrent.download_auth.ttl', 1800))->getTimestamp();
        $request->session()->put(self::DOWNLOAD_AUTH_SESSION_KEY, $authorizedUntil);

        return response()->json(['authorizedUntil' => $authorizedUntil]);
    }

    public function store(Request $request, QbittorrentApiClient $client): JsonResponse
    {
        $data = $request->validate([
            'url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $scheme = is_string($value) ? parse_url($value, PHP_URL_SCHEME) : null;

                    if (! in_array($scheme, ['http', 'https', 'magnet'], true)) {
                        $fail('种子链接必须使用 HTTP、HTTPS 或 magnet 协议。');
                    }
                },
            ],
            'account' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $client->addTorrent($data['url'], $data['account'] ?? 'default');
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json(['message' => '种子已添加到 qBittorrent。'], 201);
    }

    public function storeMteamTorrent(
        Request $request,
        MteamApiClient $mteam,
        QbittorrentApiClient $client,
    ): JsonResponse {
        if ((int) $request->session()->get(self::DOWNLOAD_AUTH_SESSION_KEY, 0) <= now()->getTimestamp()) {
            $request->session()->forget(self::DOWNLOAD_AUTH_SESSION_KEY);

            return response()->json(['message' => '下载授权已过期，请重新输入密码。'], 403);
        }

        $data = $request->validate([
            'torrent' => ['required', 'string', 'regex:/^\d+$/'],
            'account' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', Rule::in($this->categories())],
        ]);

        try {
            $client->addTorrent(
                $mteam->downloadUrl($data['torrent']),
                $data['account'],
                $data['category'],
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json(['message' => '种子已添加到 qBittorrent。'], 201);
    }

    /**
     * @return array<int, string>
     */
    private function categories(): array
    {
        $categories = config('services.qbittorrent.categories', []);

        return is_array($categories)
            ? array_values(array_unique(array_filter($categories, fn (mixed $category): bool => is_string($category) && $category !== '')))
            : [];
    }
}
