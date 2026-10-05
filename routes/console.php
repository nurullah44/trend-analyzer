<?php

use Illuminate\Support\Facades\Schedule;

// Production clock (ADR-0009): one cron line on the Linux server runs `php artisan schedule:run`
// every minute. Both runs heal what they missed, so a late or skipped tick costs nothing.
// Apple publishes App Store search weeks on Mondays at 07:00 UTC (ADR-0011): the daily run
// collects them after that, and the weekly run measures after the daily one.
Schedule::command('trends:daily')->dailyAt('09:00')->timezone('UTC')->withoutOverlapping();
Schedule::command('trends:weekly')->weeklyOn(1, '10:00')->timezone('UTC')->withoutOverlapping();
