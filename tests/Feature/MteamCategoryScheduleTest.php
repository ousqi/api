<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class MteamCategoryScheduleTest extends TestCase
{
    public function test_refresh_category_command_uses_the_default_non_overlapping_schedule(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->firstWhere('description', 'mteam:refresh-categories');

        $this->assertNotNull($event);
        $this->assertMatchesRegularExpression('/\smteam:refresh-category$/', $event->command);
        $this->assertSame('*/15 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(30, $event->expiresAt);
    }
}
