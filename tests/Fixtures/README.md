# Recorded Source fixtures

Live responses, replayed through `Http::fake()` so the suite never needs the network.

`StackExchange/` was recorded from `https://api.stackexchange.com/2.3/questions` with
`site=stackoverflow`, `sort=creation`, `order=desc` and a `fromdate`/`todate` window
covering the day in the file name:

- `2026-09-11-page-1.json` — a whole day in one page (`pagesize=100`, `has_more=false`, 49 questions).
- `2026-08-01-page-1.json`, `2026-08-01-page-2.json` — two pages (`pagesize=10`), so the
  contract test can prove pagination follows `has_more`.

To re-record, make the same request with `curl --compressed` and save the body verbatim.

`HackerNews/` was recorded from `https://hn.algolia.com/api/v1/`:

- `2026-09-21-window-{0..3}.json` — `search_by_date?tags=story` for each six-hour window of the day, `hitsPerPage=1000`; hits trimmed to the fields the collector reads.
- `volume-svelte-2026-09-21.json` — `search?query="svelte"&tags=story&hitsPerPage=0` over the week starting 2026-09-21.

`StackExchange/volume-svelte-2026-09-21.json` — `/2.3/search/advanced?q=svelte&filter=!9n30IGbb1J()` (a filter of `.total`, `.backoff`, `.quota_remaining`) over the same week.

`Wikimedia/svelte-2026-09-21.json` — `metrics/pageviews/per-article/en.wikipedia/all-access/user/Svelte/daily/20260921/20260927`.

`GoogleAds/svelte.json` was recorded on 2026-10-03 from
`POST /v25/customers/7588048331:generateKeywordHistoricalMetrics` with `keywords=["svelte"]`,
`language=languageConstants/1000`, `keywordPlanNetwork=GOOGLE_SEARCH` and no geo (worldwide);
values arrive as strings and bids are micros of the account currency (TRY).

`AppleAds/` — `POST https://api.ads.apple.com/v1/insights/apps/search-term-popularity/query` for the US storefront:

- `volume-pdf-scanner.json` was recorded on 2026-10-05 with `searchTerm EQUALS "pdf scanner"`, `fields=["searchPopularity1to100"]`
  and the weeks starting 13 September to 27 September (`WEEKLY_SUN_SAT`).
- `top-2026-09-27.json`, `top-2026-09-20.json`, `top-2026-08-30.json` (four weeks earlier, ADR-0012) are **invented** small lists in the recorded shape (`result.rows`, each with `week`,
  `countryOrRegion`, `genre`, `searchTerm` and the requested fields): a real top 500 of every genre is 7,500 rows of mostly brand names.

`AppStoreSearch/pdf-scanner.json` was recorded on 2026-10-05 from
`https://itunes.apple.com/search?term=pdf%20scanner&country=US&media=software&entity=software&limit=10`;
results trimmed to the fields the analyzer keeps.
