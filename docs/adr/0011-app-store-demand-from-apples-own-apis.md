# 0011 — App Store demand from Apple's own APIs

The first real week alarmed on three Subjects that were all tech news — an acquisition, a model release, a hardware pre-order — and the owner ruled every one noise. Hacker News and Stack Exchange show what developers talk about, not what people want an app for, which is the gap docs/sources.md said would bring the Apple Ads search-term API in. Apple publishes, per App Store genre and country, the search terms people type and how popular each one is, weekly, with 65 weeks of history. ASO tools build on exactly that, plus the iTunes Search API for who already ranks.

We decided two Apple Sources join, read for the United States:

- **Apple Ads search-term popularity** (`apple_ads`) discovers and measures. Each publication Monday it reads the top search terms of every genre for the Sunday–Saturday week that just ended. A term of two to five words is a Candidate when it is new to the top list (absent from twice its depth the week before) or climbed at least the configured number of places; at most the configured number a week, the biggest climbs first. Its genre rides along as a Label. Measurement asks for a Subject's query by name: its Volume for a week is the popularity (1–100) Apple reports for it, Apple's week answering for the ISO week that starts the next day. A week Apple has not published yet fails the measurement, so the Subject waits and the next run heals it; a term Apple does not rank has no Volume, never a zero.
- **iTunes Search** (`app_store_search`) shows Competition: the top apps App Store search returns for a Rising or Trending Subject's query, with their rating counts and update dates, as Evidence. It is never scored.

The Classifier's "is it specific" question accepts a search for a specific kind of app ("pdf scanner", "plant identifier") as specific; everything else about ADR-0006 holds. Names are Apple's own search terms, never a model's.

Nothing else from Apple is used: no App Store page, no autocomplete endpoint, no popularity endpoint that needs a browser session. Whether anyone advertises on a term stays a check the owner makes by hand on a device.

Because Apple publishes on Mondays at 07:00 UTC, the daily run collects a weekly Source on its publication day as soon as it is out, and the server's clock moves to the daily run at 09:00 and the weekly run on Mondays at 10:00 UTC, so a Monday's climbers are measured and scored the same morning.

Consequence: the Apple Ads search-term API leaves the deferred list and the iTunes Search API joins the approved list. The Apple charts stay deferred — they name single apps, and a single launch is not a trend. A Subject rising on App Store search alone reaches Rising but needs a second rising Source to alarm (Corroboration); whether App Store demand should alarm on its own waits for Verdicts on what it surfaces.
