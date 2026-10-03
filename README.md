# trend-analyzer

A private instrument that detects Subjects gaining speed on public platforms before they reach the mainstream, and publishes Alarms for an agent to review and report.

- **Domain vocabulary:** `CONTEXT.md` — use its terms in code, tests, issues and commits.
- **Decisions:** `docs/adr/` — binding until superseded.
- **Collectable sources:** `docs/sources.md` — the allowlist, with the reasons everything else is excluded.
- **Spec and work:** [issue #1](https://github.com/nurullah44/trend-analyzer/issues/1) and the tickets that follow it.

## Running it

```sh
. ~/.config/php-env.sh          # user-local PHP 8.5 and Composer
php artisan migrate && php artisan db:seed
php artisan trends:daily        # collect the missing days of the last week, discover Candidates
php artisan trends:weekly       # measure, score and move every tracked Subject; publish Alarms
php artisan trends:report       # the week's Alarms, Source health and gaps (--json for agents)
php artisan trends:show subject <slug>   # any read as JSON: report, alarms, subjects, subject, sources, gaps
php artisan trends:seed "Name" --query="search words"   # the owner's three writes: seed, label, verdict
php artisan trends:backtest     # replay the score over two years for the cases in config/trend.php
php artisan trends:status
php artisan test && vendor/bin/pint --test && vendor/bin/phpstan analyse
```

Agents: Claude Code picks up the MCP server from `.mcp.json` (`php artisan mcp:start trends`); pi uses `.pi/skills/trend-analyzer`. The pages run with `php -S 127.0.0.1:8000 -t public` (or `php artisan serve`): `/` this week's Alarms, `/subjects/{slug}`, `/sources`.

Until the move to the Linux server (ADR-0009), run them by hand: `trends:daily` every day or two (it heals the last seven days; `--days=14` reaches further), `trends:weekly` once a week, any day after Monday. Both heal what they missed. On the server, one cron line owns the clock:

```cron
* * * * * cd /path/to/trend-analyzer && php artisan schedule:run >> /dev/null 2>&1
```

Keys live in `.env` and are all optional: `JEV_API_KEY` (the Classifier — without it every Candidate waits in Backlog), `STACK_EXCHANGE_KEY` (raises the quota from 300 to 10,000 requests a day), and the `GOOGLE_ADS_*` credentials for keyword metrics.

PHP lives in `~/.local/share/php85` (extracted from Ubuntu packages, no sudo); Composer is a phar in `~/.local/bin`. SQLite is the database, in WAL mode, always on the Linux filesystem and never on `/mnt/c`.

## Shape

Laravel renders Blade pages with plain ES modules — no bundler, no JS framework, no Node. Discovery runs daily over what Stack Exchange and Hacker News published; measurement and scoring run weekly over a backfilled per-Subject series (ADR-0004). Data leaves the app through one read layer, served by an MCP server for Claude Code, an Artisan CLI for pi, and three inspection pages; the only writes are the owner's Verdicts, Labels and Seeds (ADR-0007).
