<?php

use App\Http\Controllers\AdultAccessController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\QbittorrentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MovieController::class, 'index'])->name('movies.index');
Route::get('/torrents/{categoryId}/{torrent}', [MovieController::class, 'detail'])
    ->whereNumber('categoryId')
    ->whereNumber('torrent')
    ->name('movies.detail');
Route::post('/adult-access', [AdultAccessController::class, 'store'])->name('adult-access.store');
Route::post('/qbittorrent-download/authorize', [QbittorrentController::class, 'authorizeDownload'])
    ->middleware('throttle:10,1')
    ->name('qbittorrent-download.authorize');
Route::post('/api/qbittorrent/mteam-torrents', [QbittorrentController::class, 'storeMteamTorrent'])
    ->middleware('throttle:10,1');

foreach (config('services.mteam.media_categories', []) as $categoryKey => $definition) {
    if (! is_array($definition) || ! is_string($definition['path'] ?? null) || ! is_string($definition['route'] ?? null)) {
        continue;
    }

    $route = Route::get('/'.$definition['path'], [MovieController::class, 'category'])
        ->defaults('category', $categoryKey)
        ->name($definition['route']);

    if ($categoryKey === 'adult') {
        $route->middleware('adult.access');
    }

    if (! is_array($definition['children'] ?? null) || ! is_string($definition['child_route'] ?? null)) {
        continue;
    }

    $childRoute = Route::get('/'.$definition['path'].'/{category}', [MovieController::class, 'category'])
        ->whereIn('category', array_keys($definition['children']))
        ->name($definition['child_route']);

    if ($categoryKey === 'adult') {
        $childRoute->middleware('adult.access');
    }
}
