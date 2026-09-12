# Sources

Everything the analyzer is allowed to collect from, with the facts that decided it. All figures were verified by live request on **2026-09-12** unless marked UNVERIFIED; re-check prices and terms before paying anything.

## Approved

| Source | Gives | Cost | Notes |
|---|---|---|---|
| **Google Trends TR trending RSS** — `trends.google.com/trending/rss?geo=TR` | ~10 daily Turkish trending searches with `approx_traffic` and news links | Free, no key | Undocumented and can change without notice. Gives *today's top*, not rising/breakout long tail — the official API is alpha and application-gated. |
| **GDELT DOC 2.0** — `api.gdeltproject.org/api/v2/doc/doc` | News volume timelines across 65 languages, 3-month windows | Free, no key | Best "one month early" news-emergence signal. Attribution + polite request rates requested. |
| **Reddit Data API** | Posts, comments, upvotes per subreddit and query | Free tier ~100 queries/min per client ID | **Not collectable yet — see Excluded.** |
| **Stack Exchange API** | Question volume per tag/query — people asking for help | Free | The "problems people are asking" signal, and a legal replacement for Quora. |
| **YouTube Data API v3** — `videos.list?chart=mostPopular&regionCode=TR` | Turkish trending videos, 1 unit per call | Free, 10,000 units/day | `search.list` has its own 100 calls/day bucket. ToS: no storing metadata beyond 30 days, no substitute YouTube experience. |
| **Apple Marketing Tools RSS** — `itunes.apple.com/tr/rss/topfreeapplications/...` | Turkish top-free app chart | Free, no key | One of the three Mainstream markers. Apple has retired these feeds in phases before — pin the URL and watch it. |
| **Google News RSS** — `news.google.com/rss?hl=tr&gl=TR&ceid=TR:tr` | Turkish news headlines | Free, no key | Personal use; no bulk redistribution. |
| **Wikimedia Pageviews API** | Daily article view series and top pages per project | Free, no key | Clean spike detection for a Subject that has an article. CC BY-SA attribution. |
| **Hacker News (Algolia + Firebase)** | Dev/tech emergence | Free, no key | Fair use. |
| **Product Hunt GraphQL API** | New-product emergence | Free with token | Fair-use limits unpublished; attribution required. |
| **X API** — Trends endpoint | Trending topics by location | Pay-per-use: **$0.010/request** | No free read allowance remains. At hourly polling ≈ $3/month. Optional in v1. |
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
- Anything paid must be justified by a need seen in real data, not by anticipation.
