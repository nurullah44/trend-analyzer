# 0009 — Run by hand for now; a Linux server owns the clock later

ADR-0002 put the clock in Windows Task Scheduler because the analyzer lived on a WSL machine that sleeps. The owner does not use Windows scheduling, and Reddit — the reason a residential IP mattered — is excluded. The analyzer will move to a Linux server at Hetzner, together with the cogniaagent.com site.

We decided that until the move, the owner runs `trends:daily` and `trends:weekly` by hand. Both heal what they missed: the daily run collects every missing day of the last week (`--days` reaches further back), and the weekly run backfills every week a score needs. On the server, Laravel's scheduler (`routes/console.php`) owns the clock through a single cron line running `php artisan schedule:run` every minute.

Consequence: supersedes the Windows Task Scheduler and residential-connection parts of ADR-0002. SQLite in WAL mode on the server's own disk still holds; anything needing concurrent writers is when SQLite is revisited.
