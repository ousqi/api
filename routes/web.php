<?php

use App\Http\Controllers\MovieController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MovieController::class, 'index'])->name('movies.index');

foreach (config('services.mteam.media_categories', []) as $categoryKey => $definition) {
    if (! is_array($definition) || ! is_string($definition['path'] ?? null) || ! is_string($definition['route'] ?? null)) {
        continue;
    }

    Route::get('/'.$definition['path'], [MovieController::class, 'category'])
        ->defaults('category', $categoryKey)
        ->name($definition['route']);

    if (! is_array($definition['children'] ?? null) || ! is_string($definition['child_route'] ?? null)) {
        continue;
    }

    Route::get('/'.$definition['path'].'/{category}', [MovieController::class, 'category'])
        ->whereIn('category', array_keys($definition['children']))
        ->name($definition['child_route']);
}
