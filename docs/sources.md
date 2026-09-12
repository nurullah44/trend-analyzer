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
| **X API** — Trends `GET /2/trends/by/woeid/1` | The 20–50 ranked worldwide trends (WOEID 1 = Worldwide, per X's own docs), verified live | Pay-per-use: **$0.010/request** — about $0.30/month at one request a day | **Names and rank only: no tweet counts.** `trend.fields=trend_name,tweet_count` returns `trend_name` alone. Rate limit 75 per window. Sampled across five geos, the lists were almost entirely fandom, awards shows, sport, crypto promotion and politics, with no product or technology signals — X trends corroborate at best and never discover. |
| **X API** — News search `GET /2/news/search?query=…` | Clustered news stories for a query: `name`, `hook`, `keywords`, `updated_at`; `category` is only ever `News` or `Other` | Not listed in X's published per-endpoint prices — **check the console before relying on it** | Works with **app-only auth**, so it needs no user token. It is query-driven, which makes it a per-Subject check ("is this being covered?") rather than a discovery feed. Results came back localised by the requester's location (Turkish stories from this machine), and the endpoint exposes no language or region parameter. |
| **X API** — Personalized trends `GET /2/users/personalized_trends` | `trend_name`, **`post_count`**, `category`, `trending_since` — verified live with the owner's OAuth 1.0a user token | **App-only is forbidden (verified 403)**; needs OAuth 1.0a or 2.0 user context. Per-endpoint price is not published — check console spend | **The best X surface by far**: real volumes (442 → 43K posts), recency (`13 hours ago`), and content that is AI/tech rather than fandom, because it is personalised to the owner's account. Read it as *the owner's radar*, never as a neutral global list. `category` is only ever `News` or `Other`, so topic Labels still come from our own classification. |
| **Pinterest API v5** — `/trends/keywords/{region}/top/{trend_type}` | Trend keywords and product categories | Free with approved app | Access tier for the trends scopes is UNVERIFIED; `trends.pinterest.com` is the manual fallback. |
| **Meta Ad Library API** | Ad creatives and spend ranges | Free | Low value for Turkey: outside the EU it returns only politics/social-issue ads. |

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
| **Reddit (API and RSS)** | The Responsible Builder Policy, updated 2026-09-12, requires explicit approval before any API access and a registered developer profile, and bans unapproved mining or scraping — which automated RSS polling is. An access request through Reddit's developer form is pending; revisit only if it is approved in writing. |
| **Scraping-based vendors** (Tokchart, Kalodata, Virlo, Exolyt, TipAPI, Apify actors) | Not licensed by the platforms they resell; a supplier risk the project chose not to carry. |

## Rules

- Bulk collection only through the approved list. A human may read a public page by hand; the analyzer never fetches pages.
- A Source that needs vendor approval stays out of the approved list until that approval exists in writing, whatever the technical access looks like.
- Subjects are watched **globally**. Where a Source can only be read one country at a time it carries a watched geo, recorded on the Source; Sources that are global by nature have none.
- Anything paid must be justified by a need seen in real data, not by anticipation.
