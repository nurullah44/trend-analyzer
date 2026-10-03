<?php

use Illuminate\Support\Facades\Schedule;

// Production clock (ADR-0009): one cron line on the Linux server runs `php artisan schedule:run`
// every minute. Both runs heal what they missed, so a late or skipped tick costs nothing.
Schedule::command('trends:daily')->dailyAt('06:00')->timezone('UTC')->withoutOverlapping();
Schedule::command('trends:weekly')->weeklyOn(1, '07:00')->timezone('UTC')->withoutOverlapping();
