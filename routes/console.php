<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

//Artisan::command('inspire', function () {
//    $this->comment(Inspiring::quote());
//})->purpose('Display an inspiring quote');

Schedule::command('mteam:refresh-category 419 421 402 438 429 430 432')
    ->cron('*/30 * * * *')
    ->name('mteam:refresh-categories-high')
    ->withoutOverlapping(30);

Schedule::command('mteam:refresh-category 439 420 401 404 435 403 405 453 423 448')
    ->cron('0 */2 * * *')
    ->name('mteam:refresh-categories-slow')
    ->withoutOverlapping(30);

Schedule::command('mteam:refresh-category 426 431 410 424 437 412')
    ->cron('0 */3 * * *')
    ->name('mteam:refresh-categories-adult-slow')
    ->withoutOverlapping(30);
