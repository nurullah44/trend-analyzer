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
One of the three signals that a Subject has arrived where the analyzer was trying to beat it: the Google Trends top ten, the top-free app chart, or the top news stories — each read for the watched geo. A marker has to hold rather than flash once — a single chart appearance is a Spike, not arrival.
_Avoid_: viral, popular

**Watched geo**:
The country a per-country Source is read for, such as YouTube's trending chart, Google Trends, the app chart or a news edition. The analyzer watches subjects globally: Sources that are global by nature — GDELT, Wikimedia, Hacker News, Stack Exchange, Product Hunt — have no watched geo at all.
_Avoid_: region, locale, country filter

**Spike**:
A short burst of Volume that does not persist. It is worth recording, but it does not move a Subject into Mainstream and it is not a trend on its own.
_Avoid_: trend, breakout

**Magnitude**:
How large the opportunity behind a rising Subject could become — small, medium, big, or generational. A reviewer assigns it; the analyzer never computes it.
_Avoid_: score, size, importance

**Seasonal**:
A Subject that already rose in the same period a year earlier, so its rise is expected rather than new.

### Subject states

**Backlog**:
Discovered but not yet tracked; waiting to be promoted or discarded.

**Watching**:
Tracked daily and quiet, below the rising bar.

**Rising**:
Accelerating, but not yet worth an Alarm.

**Trending**:
An Alarm is published. This is the state that earns the owner's attention.

**Mainstream**:
Reached a Mainstream marker; the Alarm is closed and the lead time is fixed.

**Detrending**:
Falling after having trended, or after a rise that failed; retained and silent.

**Archived**:
Discarded without ever having risen.

### Measures

**Volume**:
How many items mentioning the Subject a Source published on a given day.

**Velocity**:
Today's Volume measured against the Subject's own baseline — the same weekday, recent weeks, and the same period last year once a year of history exists.

**Corroboration**:
How many independent Sources are rising at the same time.

**Trend Score**:
The explainable number built from Velocity, Corroboration and Volume that moves a Subject between states. It is never a probability.
