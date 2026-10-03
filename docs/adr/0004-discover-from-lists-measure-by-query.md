# 0004 — Discover from lists, measure by query, weekly

Counting how often a Subject appears in a Source's daily top list gives counts of 0, 1 or 2 — too small for Velocity to mean anything — and a list only shows today, so every Subject starts cold. Tools that do this for a living (Exploding Topics, Glimpse) judge a candidate on a long per-subject curve, not on list appearances.

We decided collection has two jobs. **Discovery** reads a Source's list of what was published on a day (Stack Exchange questions, Hacker News stories) and only proposes candidates. **Measurement** asks a Source how many items matched one Subject's query in one week (Hacker News Algolia, Stack Exchange search, Wikimedia pageviews). Because these Sources answer for any past range, a Subject's series is backfilled the moment it is tracked, and a missed run heals itself on the next one: each run measures whatever is missing of the weeks a score needs — the baseline and the scored week. A Subject only moves on a week every Source answered.

The series is **weekly** (Monday-start ISO weeks, UTC). Weekly buckets remove the weekday effect without a weekday model, match the weekly reading ritual, and keep the query budget at one request per Subject per Source per week.

Consequence: Volume, Velocity and the Trend Score are weekly measures; the daily cadence remains only for discovery. Seasonal and Spikes wait until a year of series exists. Supersedes the daily-Volume parts of the v1 spec.
