# Recorded Source fixtures

Live responses, replayed through `Http::fake()` so the suite never needs the network.

`StackExchange/` was recorded from `https://api.stackexchange.com/2.3/questions` with
`site=stackoverflow`, `sort=creation`, `order=desc` and a `fromdate`/`todate` window
covering the day in the file name:

- `2026-09-11-page-1.json` — a whole day in one page (`pagesize=100`, `has_more=false`, 49 questions).
- `2026-08-01-page-1.json`, `2026-08-01-page-2.json` — two pages (`pagesize=10`), so the
  contract test can prove pagination follows `has_more`.

To re-record, make the same request with `curl --compressed` and save the body verbatim.
