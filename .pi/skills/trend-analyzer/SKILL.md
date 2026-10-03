---
name: trend-analyzer
description: Read what the owner's trend analyzer found — this week's Alarms, Subjects, their weekly series and Evidence, Source health and gaps — and record the owner's Verdicts, Labels and Seeds when the owner says so.
allowed-tools: Bash(php artisan trends:*)
---

# trend-analyzer

A private instrument that detects Subjects gaining speed before they reach the mainstream. Statistics detect; you read the data and talk it over with the owner. Vocabulary is in `CONTEXT.md` — use its words (Alarm, Subject, Evidence, Verdict, Label, Seed, Mainstream).

Run every command from the repository root after `. ~/.config/php-env.sh`.

## Read (always allowed)

```sh
php artisan trends:report                    # this week's Alarms, Source health and gaps, as Markdown
php artisan trends:show report [--week=YYYY-MM-DD]
php artisan trends:show alarms [--week=YYYY-MM-DD]   # open Alarms by default
php artisan trends:show subjects [--state=rising] [--label=ai]
php artisan trends:show subject <slug>       # series, score, Events, Alarms, Items
php artisan trends:show sources
php artisan trends:show gaps
```

Every `trends:show` answer is JSON. An Alarm's `evidence` holds the numbers behind it — weekly Volumes per Source, Velocity, Corroboration, the Trend Score, the matching Items with links, and links to check each Source by hand. Quote them; never invent a number.

## Write (only when the owner says so, in this conversation)

```sh
php artisan trends:verdict <alarm-id> worth_considering|noise [--note="one line"] [--magnitude=small|medium|big|generational]
php artisan trends:label <slug> <label> [--remove]
php artisan trends:seed "<name, five words or fewer>" [--query="search words"] [--label=ai]
```

## Never

- Never run `trends:daily`, `trends:weekly`, `trends:collect` or `trends:discover`: collection is the app's own scheduled job.
- Never edit the database, an Alarm, Evidence or a series, and never fetch web pages on the analyzer's behalf.
- Never decide on the owner's behalf that something is worth building: present the Evidence, the owner rules.
