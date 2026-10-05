<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_the_server_runs_daily_and_weekly_without_overlap(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->mapWithKeys(fn (Event $event) => [trim(str($event->command)->after('artisan')->replace(["'", '"'], '')) => $event]);

        $this->assertSame('0 9 * * *', $events['trends:daily']->expression);
        $this->assertSame('0 10 * * 1', $events['trends:weekly']->expression);
        $this->assertTrue($events['trends:daily']->withoutOverlapping && $events['trends:weekly']->withoutOverlapping);
    }
}
