<?php

/*
| The approved Sources, mirroring docs/sources.md. Adding a Source means one
| entry here plus one implementation of the collection contract — nothing else.
|
| The analyzer watches subjects globally. A few Sources are only readable one
| country at a time — those carry a `geo`; Sources that are global by nature
| leave it null.
|
| `enabled` false means it is allowed but deliberately not collected yet.
*/
return [
    'default_geo' => env('TREND_GEO', 'US'),

    'sources' => [
        ['key' => 'google_trends', 'name' => 'Google Trends trending searches', 'kind' => 'rss', 'geo' => 'US', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://trends.google.com/trending/rss?geo=US'],
        ['key' => 'gdelt', 'name' => 'GDELT DOC 2.0', 'kind' => 'api', 'geo' => null, 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://api.gdeltproject.org/api/v2/doc/doc'],
        ['key' => 'stack_exchange', 'name' => 'Stack Exchange API', 'kind' => 'api', 'geo' => null, 'enabled' => true, 'cost_note' => 'free, no key needed at this volume', 'docs_url' => 'https://api.stackexchange.com/docs'],
        ['key' => 'youtube_trending', 'name' => 'YouTube Data API — trending videos', 'kind' => 'api', 'geo' => 'US', 'enabled' => true, 'cost_note' => 'free, 10k units/day; needs a Google Cloud API key', 'docs_url' => 'https://developers.google.com/youtube/v3/docs/videos/list'],
        ['key' => 'apple_chart', 'name' => 'Apple Marketing Tools — top free apps', 'kind' => 'rss', 'geo' => 'US', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://rss.applemarketingtools.com/'],
        ['key' => 'google_news', 'name' => 'Google News RSS', 'kind' => 'rss', 'geo' => 'US', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://news.google.com/rss?hl=en-US&gl=US&ceid=US:en'],
        ['key' => 'wikimedia', 'name' => 'Wikimedia Pageviews API', 'kind' => 'api', 'geo' => null, 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://wikimedia.org/api/rest_v1/'],
        ['key' => 'hacker_news', 'name' => 'Hacker News (Algolia + Firebase)', 'kind' => 'api', 'geo' => null, 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://hn.algolia.com/api'],
        ['key' => 'product_hunt', 'name' => 'Product Hunt GraphQL API', 'kind' => 'api', 'geo' => null, 'enabled' => true, 'cost_note' => 'free; needs a developer token', 'docs_url' => 'https://api.producthunt.com/v2/docs'],
        ['key' => 'reddit', 'name' => 'Reddit Data API', 'kind' => 'api', 'geo' => null, 'enabled' => false, 'cost_note' => 'blocked: approval and a registered developer profile required; access request pending', 'docs_url' => 'https://redditinc.com/policies/responsible-builder-policy'],
        ['key' => 'x_trends', 'name' => 'X API — Trends', 'kind' => 'api', 'geo' => null, 'enabled' => false, 'cost_note' => 'pay-per-use, $0.010 per request', 'docs_url' => 'https://docs.x.com/x-api/trends/introduction'],
        ['key' => 'pinterest', 'name' => 'Pinterest API v5 — Trends', 'kind' => 'api', 'geo' => 'US', 'enabled' => false, 'cost_note' => 'free with an approved app', 'docs_url' => 'https://developers.pinterest.com/docs/api/v5/'],
        ['key' => 'meta_ad_library', 'name' => 'Meta Ad Library API', 'kind' => 'api', 'geo' => null, 'enabled' => false, 'cost_note' => 'free; outside the EU only politics and social-issue ads are returned', 'docs_url' => 'https://developers.facebook.com/docs/graph-api/reference/ads_archive/'],
    ],
];
