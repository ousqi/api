<?php

use App\Http\Controllers\AdultAccessController;
use App\Http\Controllers\MovieController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MovieController::class, 'index'])->name('movies.index');
Route::post('/adult-access', [AdultAccessController::class, 'store'])->name('adult-access.store');

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
