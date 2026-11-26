<?php

use App\Http\Controllers\QbittorrentController;
use App\Http\Controllers\TtsController;
use App\Http\Middleware\LocalTtsAuthentication;
use Illuminate\Support\Facades\Route;

Route::middleware([LocalTtsAuthentication::class.':api', 'throttle:15,1'])->prefix('tts')->group(function (): void {
    Route::get('voices', [TtsController::class, 'voices']);
    Route::post('preview', [TtsController::class, 'preview']);
});

Route::middleware([LocalTtsAuthentication::class.':api', 'throttle:10,1'])->prefix('qbittorrent')->group(function (): void {
    Route::post('torrents', [QbittorrentController::class, 'store']);
});
