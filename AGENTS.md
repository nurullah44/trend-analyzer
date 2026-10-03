# Working in this repository

Read these first, in order: `CONTEXT.md` (the domain glossary and the only source of vocabulary), `docs/agents/domain.md`, `docs/adr/` (binding decisions), `docs/sources.md` (the collection allowlist).

## Rules that are easy to get wrong

- **Write in the glossary's terms.** Alarm, Subject, Evidence, Verdict, Label, Source, Discovery, Measurement, Candidate, Classifier, Seed, Mainstream marker, Magnitude and the Subject states mean exactly what `CONTEXT.md` says. If a term is missing, add it there before using it in code.
- **Laravel only.** Plain Blade plus unbundled ES modules. No bundler, no JS framework, no Node, no npm. Do not add `@vite`.
- **SQLite only, on the Linux filesystem.** WAL mode, never `/mnt/c`.
- **No scraping, ever.** Collection uses the Sources in `docs/sources.md` and nothing else, and no agent fetches or reads external pages on the analyzer's behalf.
- **Statistics detect, agents explain.** No model decides whether something is emerging and no model writes an Alarm. The Classifier (Jev) only answers typed questions about a Candidate — is it a specific thing, which Label — and names come from the Source's own identifiers (ADR-0006).
- **Agents read and discuss, plus three writes on the owner's word.** The MCP server and the CLI give an agent the collected data: it reads and talks the results over with the owner. When the owner says so it may record a Verdict, set a Label, or add a Seed (ADR-0007), and start the two scheduled runs, `trends:daily` and `trends:weekly`, then report what came out (ADR-0010). Nothing else: it never collects a single Source on its own, never edits an Alarm, Evidence or a series.
- **Frontend uses the Clay design system** (`~/.claude/opendesign/design-systems/clay`), its tokens copied into `public/css`.

## Environment

```sh
. ~/.config/php-env.sh     # user-local PHP 8.5 + Composer, no sudo needed
php artisan test           # the suite must pass with no network access
vendor/bin/pint --test     # style
vendor/bin/phpstan analyse # static analysis (Larastan)
```

## Work

Issues live in GitHub Issues on `nurullah44/trend-analyzer`; see `docs/agents/issue-tracker.md`. Tickets are vertical slices and declare their blockers — work the frontier, keep the suite green, and commit per ticket.

## Subagents and review

- Subagents and reviewers run through the OpenAI Codex CLI on `gpt-6.1-sol` at `high` reasoning, never another model or a lower level. Dispatch subagents only when really needed.
- Every ticket is reviewed before its commit, read-only:
  `codex review --uncommitted -c model="gpt-6.1-sol" -c model_reasoning_effort="high"`
  Fix every critical or correctness finding, then commit.
