<?php

/*
| The approved Sources, mirroring docs/sources.md. Adding a Source means one
| entry here plus one implementation of the collection contract — nothing else.
| `enabled` false means it is allowed but deliberately not collected yet.
*/
return [
    'sources' => [
        ['key' => 'google_trends_tr', 'name' => 'Google Trends Turkey trending RSS', 'kind' => 'rss', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://trends.google.com/trending/rss?geo=TR'],
        ['key' => 'gdelt', 'name' => 'GDELT DOC 2.0', 'kind' => 'api', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://api.gdeltproject.org/api/v2/doc/doc'],
        ['key' => 'reddit', 'name' => 'Reddit Data API', 'kind' => 'api', 'enabled' => true, 'cost_note' => 'free tier, commercial use needs an agreement', 'docs_url' => 'https://www.reddit.com/dev/api/'],
        ['key' => 'stack_exchange', 'name' => 'Stack Exchange API', 'kind' => 'api', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://api.stackexchange.com/docs'],
        ['key' => 'youtube_tr', 'name' => 'YouTube Data API — Turkish trending', 'kind' => 'api', 'enabled' => true, 'cost_note' => 'free, 10k units/day', 'docs_url' => 'https://developers.google.com/youtube/v3/docs/videos/list'],
        ['key' => 'apple_tr', 'name' => 'Apple Marketing Tools — Turkish top free chart', 'kind' => 'rss', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://rss.applemarketingtools.com/'],
        ['key' => 'google_news_tr', 'name' => 'Google News RSS (Turkish)', 'kind' => 'rss', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://news.google.com/rss?hl=tr&gl=TR&ceid=TR:tr'],
        ['key' => 'wikimedia', 'name' => 'Wikimedia Pageviews API', 'kind' => 'api', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://wikimedia.org/api/rest_v1/'],
        ['key' => 'hacker_news', 'name' => 'Hacker News (Algolia + Firebase)', 'kind' => 'api', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://hn.algolia.com/api'],
        ['key' => 'product_hunt', 'name' => 'Product Hunt GraphQL API', 'kind' => 'api', 'enabled' => true, 'cost_note' => 'free with a token', 'docs_url' => 'https://api.producthunt.com/v2/docs'],
        ['key' => 'x_trends', 'name' => 'X API — Trends', 'kind' => 'api', 'enabled' => false, 'cost_note' => 'pay-per-use, $0.010 per request', 'docs_url' => 'https://docs.x.com/x-api/trends/introduction'],
        ['key' => 'pinterest', 'name' => 'Pinterest API v5 — Trends', 'kind' => 'api', 'enabled' => false, 'cost_note' => 'free with an approved app', 'docs_url' => 'https://developers.pinterest.com/docs/api/v5/'],
        ['key' => 'meta_ad_library', 'name' => 'Meta Ad Library API', 'kind' => 'api', 'enabled' => false, 'cost_note' => 'free; Turkish coverage limited to politics and social-issue ads', 'docs_url' => 'https://developers.facebook.com/docs/graph-api/reference/ads_archive/'],
    ],
];
