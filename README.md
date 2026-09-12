# trend-analyzer

A private instrument that detects Subjects gaining speed on public platforms before they reach the mainstream, and publishes Alarms for an agent to review and report.

- **Domain vocabulary:** `CONTEXT.md` — use its terms in code, tests, issues and commits.
- **Decisions:** `docs/adr/` — binding until superseded.
- **Collectable sources:** `docs/sources.md` — the allowlist, with the reasons everything else is excluded.
- **Spec and work:** [issue #1](https://github.com/nurullah44/trend-analyzer/issues/1) and the tickets that follow it.

## Running it

```sh
. ~/.config/php-env.sh          # user-local PHP 8.5 and Composer
php artisan trends:status
php artisan serve
php artisan test
```

PHP lives in `~/.local/share/php85` (extracted from Ubuntu packages, no sudo); Composer is a phar in `~/.local/bin`. SQLite is the database, in WAL mode, always on the Linux filesystem and never on `/mnt/c`.

## Shape

Laravel renders Blade pages with plain ES modules — no bundler, no JS framework, no Node. Collection runs daily, analysis weekly, both triggered from Windows Task Scheduler so they survive the WSL distro being asleep. Data leaves the app through two read-only doors over one query layer: an MCP server for Claude Code and an Artisan CLI for pi.
