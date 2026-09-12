# Sources

Everything the analyzer is allowed to collect from, with the facts that decided it. All figures were verified by live request on **2026-09-12** unless marked UNVERIFIED; re-check prices and terms before paying anything.

## Approved

| Source | Gives | Cost | Notes |
|---|---|---|---|
| **Google Trends trending RSS** — `trends.google.com/trending/rss?geo=GLOBAL` | Daily trending searches, worldwide, with `approx_traffic` and news links | Free, no key | Undocumented and can change without notice. `geo=GLOBAL` is verified working (5 multilingual items); `WW`, `Worldwide` and `ALL` are rejected with 400, and an empty geo silently falls back to the requester's IP country. Gives *today's top*, not the rising/breakout long tail — the official API is alpha and application-gated. |
| **GDELT DOC 2.0** — `api.gdeltproject.org/api/v2/doc/doc` | News volume timelines across 65 languages, 3-month windows | Free, no key | Best "one month early" news-emergence signal. Attribution + polite request rates requested. |
| **Reddit Data API** | Posts, comments, upvotes per subreddit and query | Free tier ~100 queries/min per client ID | **Not collectable yet — see Excluded.** |
| **Stack Exchange API** | Question volume per tag/query — people asking for help | Free, no key needed at this volume (a key raises the daily quota) | The "problems people are asking" signal, and a legal replacement for Quora. |
| **YouTube Data API v3** — `videos.list?chart=mostPopular&regionCode=US` | Trending videos for one region; `regionCode` takes an ISO country code and there is **no worldwide value**, so it stays on the fallback geo. Content skews to entertainment, so it corroborates rather than discovers. 1 unit per call | Free, 10,000 units/day — **needs a Google Cloud API key** | `search.list` has its own 100 calls/day bucket. ToS: no storing metadata beyond 30 days, no substitute YouTube experience. |
| **Apple Marketing Tools RSS** — `itunes.apple.com/us/rss/topfreeapplications/...` | Top-free app chart, per storefront. There is **no worldwide chart**: `/global/` returns 404 | Free, no key | One of the three Mainstream markers. Apple has retired these feeds in phases before — pin the URL and watch it. |
| **Google News RSS (topic feeds)** — `news.google.com/rss/headlines/section/topic/<TOPIC>?hl=en-US&gl=US&ceid=US:en` | Global news sections: `TECHNOLOGY`, `BUSINESS`, `SCIENCE`, `WORLD` (verified, 68 items on `WORLD`) | Free, no key | Read as topic sections rather than one country's front page, so the signal is not national. A country front page is still available if a local view is ever wanted. Personal use; no bulk redistribution. |
| **Wikimedia Pageviews API** | Daily article view series and top pages per project | Free, no key | Clean spike detection for a Subject that has an article. CC BY-SA attribution. |
| **Hacker News (Algolia + Firebase)** | Dev/tech emergence | Free, no key | Fair use. |
| **Product Hunt GraphQL API** | New-product emergence | Free — **needs a developer token** from a Product Hunt app | Fair-use limits unpublished; attribution required. |
| **Pinterest API v5** — `/trends/keywords/{region}/top/{trend_type}` | Trend keywords and product categories | Free with approved app | Access tier for the trends scopes is UNVERIFIED; `trends.pinterest.com` is the manual fallback. |
| **Apple Ads Platform API v1 — `POST /v1/insights/apps/search-term-popularity/query`** | The **most popular App Store search terms by search volume** for a genre and country, with `searchPopularity1to100`, `searchPopularity1to5` and `rankInGenre`, weekly or monthly. Genre granularity is coarse — 15 values such as `PRODUCTIVITY_UTILITIES`, `HEALTH_FITNESS`, `BUSINESS`, `FINANCE` | Free to call, but needs an **Apple Ads account**, an API user role, and OAuth 2.0 with an ECDSA-signed JWT. Apple Ads covers the Turkish storefront (live since 3 Oct 2024) | **App Store demand, by category, at no cost** — the ASO signal the App Store charts cannot give. Supersedes the old Campaign Management API, which sunsets 26 Jan 2027. Whether it works on an account with no ad spend is **unverified**. `POST /v1/suggestions/keywords/query` adds keyword suggestions with a `popularity` score but needs an app being promoted. |
| **Google Ads API — `GenerateKeywordHistoricalMetrics`** | Average monthly searches (past 12 months), approximate monthly volume, competition level and index, for named keywords, with geo and language targets | Free per call; needs a Google Ads **manager account**, a **developer token with Basic access** (Test-level tokens return no real data) and OAuth credentials — no ad spend required | The Keyword Planner numbers, read programmatically, and the sizing half of validation. **Verified live 2026-09-12.** The path took: correct customer id → account reactivated → Cloud project raised from Test to **Explorer** → Explorer refused keyword planning (`DEVELOPER_TOKEN_NOT_APPROVED`) → **Basic access granted after brand verification** (published OAuth app, `cogniaagent.com` verified in Search Console, verified branding). Customer id `7588048331`, currency TRY, time zone Europe/Istanbul.

Working request — `POST /v25/customers/{id}:generateKeywordHistoricalMetrics` with `keywords`, `geo_target_constants` (2840 = US, 2792 = Türkiye), `language` (`languageConstants/1000` = English) and `keyword_plan_network: GOOGLE_SEARCH`. Each result carries **`keywordMetrics`** (note the field name, not `metrics`): `avgMonthlySearches`, `competition`, `competitionIndex`, `lowTopOfPageBidMicros`, `highTopOfPageBidMicros` and **`monthlySearchVolumes`** — twelve points, one per month. All values arrive as strings.

That is three signals at once: **volume** (how many search a month), **direction** (the twelve points), and **what advertisers pay per click** (the bid range) — the last being a direct monetisation signal. Used only on Subjects that already reached Rising, never for discovery. |
| **Meta Ad Library API** | Ad creatives and spend ranges | Free | Low value for Turkey: outside the EU it returns only politics/social-issue ads. |

## Candidate: keyword volume without a Google Ads account

Google's own route to keyword volumes is effectively closed for a private tool: **Explorer** access cannot call keyword planning (verified — `DEVELOPER_TOKEN_NOT_APPROVED: "This method is not allowed for use with explorer access"`), and **Basic** requires brand verification, which means owning a domain verified in Google Search Console plus homepage and privacy-policy URLs.

**DataForSEO Google Ads API** sells the same Keyword Planner data without any Google account:

| | |
|---|---|
| Price | **$0.09 per task** live (~7s), **$0.06 per task** in the standard queue (up to 45 min) — each task covers up to **1,000 keywords** |
| Minimum | **$50** one-time credit top-up |
| Gives | search volume, impressions, average CPC, competition, monthly volumes, geo down to city level |
| Also sells | a **Google Trends API** product — the interest-over-time series Google's own alpha API keeps gated |
| Caveat | a reseller, so the upstream provenance is theirs, not Google's contract with us |

At this project's volume — a handful of keywords a week — the $50 credit would last years.

## Excluded

| Source | Why |
|---|---|
| **TikTok Research API** | Academic/non-profit only, and Turkey is not an eligible region. |
| **TikTok Commercial Content API** | EU ad data only. |
| **TikTok organic trends (any vendor)** | No sanctioned API exists; every vendor selling TR TikTok trends self-collects. Only TikTok's own Creative Center is free and TR-scoped, and it is hand-browse only. |
| **Instagram / Facebook / Threads trends** | No public trend API; CrowdTangle shut down 2024-08-14 and Meta Content Library is academic-only. |
| **Quora** | No API; its robots.txt forbids automated access of any kind. |
| **Google Play charts** | No API — only scraping — and paid third parties start in the hundreds per month. |
| **Amazon** | Creators API requires an Associates account with qualifying sales; PA-API 5 is being sunset. |
| **Kickstarter / Indiegogo** | No licensed API; automated access is fragile and against terms. |
| **X / Twitter (any surface)** | Parked after measuring all three: personalized trends are algorithmic and shaped by the owner's engagement; list and Following timelines cost $0.005 per post read; the public trends feed is names-only and reads as fandom, sport and politics. **Topic tabs (Tech, Business, Science) have no API at all** — verified against X's full public spec, which contains no `/2/topics/*` endpoint, and "For you" is not exposed either. Revisit if X publishes topic feeds, or if the owner chooses to fund roughly $3/month for a weekly timeline read. |
| **Reddit (API and RSS)** | The Responsible Builder Policy, updated 2026-09-12, requires explicit approval before any API access and a registered developer profile, and bans unapproved mining or scraping — which automated RSS polling is. An access request through Reddit's developer form is pending; revisit only if it is approved in writing. |
| **Scraping-based vendors** (Tokchart, Kalodata, Virlo, Exolyt, TipAPI, Apify actors) | Not licensed by the platforms they resell; a supplier risk the project chose not to carry. |

## Roles

A Source is collected for one or more jobs, recorded on the Source itself:

| Role | Job | Who does it now |
|---|---|---|
| **discovery** | Surfaces Subjects nobody named | Stack Exchange, Hacker News, Product Hunt, Apple charts by genre, YouTube trending **by category** |
| **marker** | Confirms a Subject has arrived | Google Trends trending, Apple charts |
| **validation** | Sizes or shapes a Subject already tracked | Wikimedia pageviews, Google Ads keyword metrics |

The rule that decides this: **demand and supply are signal; attention and news are dirt.** A Source that only reports what people are reading about — news volume, generic trending lists — is disabled no matter how free it is, because it cannot tell us that somebody wants something.

## Rules

- Bulk collection only through the approved list. A human may read a public page by hand; the analyzer never fetches pages.
- A Source that needs vendor approval stays out of the approved list until that approval exists in writing, whatever the technical access looks like.
- Free sources may be collected daily at no cost; the analysis and report run weekly. Only paid sources are constrained by the weekly cadence.
- Subjects are watched **globally**. Where a Source can only be read one country at a time it carries a watched geo, recorded on the Source; Sources that are global by nature have none.
- Anything paid must be justified by a need seen in real data, not by anticipation.
