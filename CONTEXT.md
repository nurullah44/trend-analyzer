# trend-analyzer

A private instrument that detects Subjects gaining speed on public platforms before they reach the mainstream, and publishes Alarms for an agent to review and report. It collects evidence; it does not decide what is worth building.

## Language

**Alarm**:
Research output the analyzer publishes when a Subject crosses a line the owner set. It carries Evidence rather than a verdict, and is reviewed by an agent before reaching the owner.
_Avoid_: alert, notification, ping, trend

**Subject**:
A specific thing the analyzer can name in five words or fewer and re-find with a query — never a broad field like “AI”. It is stable enough to be re-measured over time, and problems or needs appear as Evidence about it rather than as Subjects of their own.
_Avoid_: topic, keyword, hashtag, query

**Evidence**:
A verifiable observation supporting or weakening a Subject's rise: source, timestamp, measured quantity, and verbatim text or link. Evidence is collected, never generated.
_Avoid_: insight, take, summary

**Measured quantity**:
The number a Source itself reports for one thing it published — a question's score, a video's view count, an app's chart rank. The analyzer never computes it.
_Avoid_: signal, weight, metric

**Verdict**:
The ruling a reviewer makes on an Alarm — worth considering, or noise — recorded so thresholds can later be tuned against real outcomes instead of guesses.
_Avoid_: score, rating, feedback

**Label**:
A freely assigned tag on a Subject, such as an AI, mobile-app, web-app, or utility-need tag. Labels never restrict what the analyzer collects; they decide which Alarms reach the owner.
_Avoid_: category, vertical, filter

**Source**:
An official API, RSS feed, public dataset, or licensed third-party provider the analyzer is permitted to collect from. Pages are never Sources, and no agent fetches external pages on the analyzer's behalf.
_Avoid_: page, website, scrape, feed

**Mainstream marker**:
A line on one of the Subject's own measures that says it has arrived where the analyzer was trying to beat it: its Wikipedia article's weekly pageviews, or its Google Ads average monthly searches, at or above a configured threshold. A marker has to hold — two consecutive weeks or months over the line — rather than flash once.
_Avoid_: viral, popular

**Watched geo**:
The country a per-country Source is read for, such as YouTube's trending chart or the app chart. The analyzer watches subjects globally: Sources that are global by nature — Wikimedia, Hacker News, Stack Exchange, Product Hunt, Google Ads read without a geo — have no watched geo at all.
_Avoid_: region, locale, country filter

**Spike**:
A short burst of Volume that does not persist. It is not a trend on its own. Not recorded yet: it waits until the series is long enough to tell a burst from a rise.
_Avoid_: trend, breakout

**Magnitude**:
How large the opportunity behind a rising Subject could become — small, medium, big, or generational. A reviewer assigns it; the analyzer never computes it.
_Avoid_: score, size, importance

**Seasonal**:
A Subject that already rose in the same period a year earlier, so its rise is expected rather than new. Not computed until a year of series exists.

**Discovery**:
Reading what a Source published on one day only to propose candidates. Discovery never measures anything.

**Measurement**:
Asking a Source how many items matched one Subject's query in one week. Measurement Sources answer for any past range, so a newly tracked Subject is backfilled at once.

**Candidate**:
A tag or phrase discovery proposed that is not yet a Subject. The Classifier decides whether it is tracked, waits in Backlog, or is dropped.

**Classifier**:
Jev, TypeSafe's System One model. It answers typed questions about a candidate — is it a specific, nameable thing, and which Label fits — with probabilities. It never measures, never scores, and never decides that something is emerging.

**Seed**:
A Subject the owner names by hand, with its query. It skips the Classifier and starts in Watching.

**Lead time**:
The days from a Subject's first Alarm to its Mainstream moment — the head start the analyzer earned.

### Subject states

**Backlog**:
Discovered but not yet tracked — the Classifier was unsure or not configured; waiting for the owner to promote it with a seed.

**Watching**:
Measured weekly and quiet, below the rising bar.

**Rising**:
Accelerating, but not yet worth an Alarm.

**Trending**:
An Alarm is published. This is the state that earns the owner's attention.

**Mainstream**:
Reached a Mainstream marker; the Alarm is closed and the lead time is fixed.

**Detrending**:
Falling after having trended, or after a rise that failed; retained and silent.

**Archived**:
Left Watching after thirty days without ever having risen. No longer measured; its rows are kept.

### Measures

**Volume**:
How many items matching the Subject's query a Source published in a given week.

**Velocity**:
This week's Volume against the Subject's own trailing eight weeks on the same Source: how many spreads above the baseline's median it sits. A year-ago comparison joins once a year of history exists.

**Corroboration**:
How many independent Sources show the Subject rising in the same week.

**Trend Score**:
The explainable number built from Velocity, Corroboration and Volume that moves a Subject between states. It is never a probability.
