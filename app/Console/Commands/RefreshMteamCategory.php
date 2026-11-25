<?php

namespace App\Console\Commands;

use App\Services\MovieTorrentCache;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RefreshMteamCategory extends Command
{
    protected $signature = 'mteam:refresh-category {categoryIds?* : 要刷新的分类 ID（可传多个，省略时刷新全部已配置分类）}';

    protected $description = 'Refresh cached MTeam torrent previews for all configured categories or the supplied category IDs.';

    public function handle(MovieTorrentCache $movieTorrents): int
    {
        $providedCategoryIds = $this->argument('categoryIds');

        if (! is_array($providedCategoryIds)) {
            $this->error('分类 ID 参数无效。');

            return self::FAILURE;
        }

        if ($providedCategoryIds === []) {
            $categoryIds = $movieTorrents->configuredCategoryIds();
        } else {
            $categoryIds = [];

            foreach ($providedCategoryIds as $categoryId) {
                if (! is_string($categoryId) || ! ctype_digit($categoryId) || ! $movieTorrents->isConfiguredCategoryId($categoryId)) {
                    $this->error('分类 ID 无效或未在 MTeam 媒体分类中配置。');

                    return self::FAILURE;
                }

                if (! in_array($categoryId, $categoryIds, true)) {
                    $categoryIds[] = $categoryId;
                }
            }
        }

        if ($categoryIds === []) {
            $this->error('未配置可刷新的 MTeam 分类 ID。');

            return self::FAILURE;
        }

        $successfulCategories = 0;
        $failedCategories = 0;
        $cachedTorrents = 0;

        foreach ($categoryIds as $configuredCategoryId) {
            try {
                $count = count($movieTorrents->refreshCategory($configuredCategoryId));
                $successfulCategories++;
                $cachedTorrents += $count;
                $this->info("Cached {$count} MTeam category {$configuredCategoryId} torrents.");
            } catch (InvalidArgumentException) {
                $failedCategories++;
                $this->error("MTeam 分类 {$configuredCategoryId} 无效或未配置。");
            } catch (\Throwable) {
                $failedCategories++;
                $this->error("MTeam 分类 {$configuredCategoryId} 快取刷新失败。");
            }
        }

        $this->info("MTeam 分类刷新完成：成功 {$successfulCategories}，失败 {$failedCategories}，已快取 {$cachedTorrents} 个种子。");

        return $failedCategories === 0 ? self::SUCCESS : self::FAILURE;
    }
}
