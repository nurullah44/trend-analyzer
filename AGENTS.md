# Working in this repository

Read these first, in order: `CONTEXT.md` (the domain glossary and the only source of vocabulary), `docs/agents/domain.md`, `docs/adr/` (binding decisions), `docs/sources.md` (the collection allowlist).

## Rules that are easy to get wrong

- **Write in the glossary's terms.** Alarm, Subject, Evidence, Verdict, Label, Source, Mainstream marker, Spike, Magnitude and the Subject states mean exactly what `CONTEXT.md` says. If a term is missing, add it there before using it in code.
- **Laravel only.** Plain Blade plus unbundled ES modules. No bundler, no JS framework, no Node, no npm. Do not add `@vite`.
- **SQLite only, on the Linux filesystem.** WAL mode, never `/mnt/c`.
- **No scraping, ever.** Collection uses the Sources in `docs/sources.md` and nothing else, and no agent fetches or reads external pages on the analyzer's behalf.
- **Statistics detect, agents explain.** No model decides whether something is emerging and no model writes an Alarm. A model may only group and name Subjects, and tidy report output.
- **Read-only agent doors.** The MCP server and the CLI expose reads; recording a Verdict is the only write.

## Environment

```sh
. ~/.config/php-env.sh     # user-local PHP 8.5 + Composer, no sudo needed
php artisan test           # the suite must pass with no network access
```

## Work

Issues live in GitHub Issues on `nurullah44/trend-analyzer`; see `docs/agents/issue-tracker.md`. Tickets are vertical slices and declare their blockers — work the frontier, keep the suite green, and commit per ticket.
