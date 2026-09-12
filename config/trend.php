<?php

/*
| The approved Sources, mirroring docs/sources.md. Adding a Source means one
| entry here plus one implementation of the collection contract — nothing else.
|
| `roles` says what job the Source does:
|   discovery  — surfaces Subjects we did not know about
|   marker     — tells us a Subject has reached the mainstream
|   validation — sizes or shapes a Subject we already track
|
| The analyzer watches subjects globally. A few Sources are only readable one
| country at a time — those carry a `geo`; Sources that are global by nature
| leave it null. `enabled` false means allowed but deliberately not collected.
*/
return [
    // The fallback for Sources that can only be read one country at a time.
    // Global or the United States only — Türkiye is never used as a geo.
    'default_geo' => env('TREND_GEO', 'US'),

    'sources' => [
        // Demand: people asking for help, by category.
        ['key' => 'stack_exchange', 'name' => 'Stack Exchange API', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery', 'enabled' => true, 'cost_note' => 'free, no key needed at this volume', 'docs_url' => 'https://api.stackexchange.com/docs'],
        ['key' => 'hacker_news', 'name' => 'Hacker News (Algolia + Firebase)', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://hn.algolia.com/api'],

        // Supply: what is actually launching and being adopted.
        ['key' => 'product_hunt', 'name' => 'Product Hunt GraphQL API', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery', 'enabled' => true, 'cost_note' => 'free; needs a developer token', 'docs_url' => 'https://api.producthunt.com/v2/docs'],
        ['key' => 'apple_chart', 'name' => 'Apple Marketing Tools — top free apps by genre', 'kind' => 'rss', 'geo' => 'US', 'roles' => 'discovery,marker', 'enabled' => true, 'cost_note' => 'free; read per genre rather than as one top-100 list', 'docs_url' => 'https://rss.applemarketingtools.com/'],

        // Interest, but only ever read through a category filter.
        ['key' => 'youtube_trending', 'name' => 'YouTube Data API — trending by category (28 Science & Technology, 27 Education)', 'kind' => 'api', 'geo' => 'US', 'roles' => 'discovery', 'enabled' => true, 'cost_note' => 'free, 10k units/day; needs a Google Cloud API key; read with videoCategoryId, never the unfiltered chart', 'docs_url' => 'https://developers.google.com/youtube/v3/docs/videos/list'],

        // Trajectory only.
        ['key' => 'wikimedia', 'name' => 'Wikimedia Pageviews API', 'kind' => 'api', 'geo' => null, 'roles' => 'validation', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://wikimedia.org/api/rest_v1/'],

        // Marker only: today's top searches tell us something has arrived, never that something is coming.
        ['key' => 'google_trends', 'name' => 'Google Trends trending searches', 'kind' => 'rss', 'geo' => 'GLOBAL', 'roles' => 'marker', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://trends.google.com/trending/rss?geo=GLOBAL'],

        // Parked or blocked, kept registered so the reason is visible.
        ['key' => 'google_news', 'name' => 'Google News RSS (topic feeds)', 'kind' => 'rss', 'geo' => 'GLOBAL', 'roles' => 'marker', 'enabled' => false, 'cost_note' => 'disabled: attention and news, not demand', 'docs_url' => 'https://news.google.com/rss/headlines/section/topic/TECHNOLOGY?hl=en-US&gl=US&ceid=US:en'],
        ['key' => 'gdelt', 'name' => 'GDELT DOC 2.0', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'disabled: news volume measures attention, not demand', 'docs_url' => 'https://api.gdeltproject.org/api/v2/doc/doc'],
        ['key' => 'reddit', 'name' => 'Reddit Data API', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'blocked: approval and a registered developer profile required; access request pending', 'docs_url' => 'https://redditinc.com/policies/responsible-builder-policy'],
        ['key' => 'x_trends', 'name' => 'X API', 'kind' => 'api', 'geo' => 'GLOBAL', 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'parked: algorithmic, paid per post, or names-only; topic feeds have no API', 'docs_url' => 'https://docs.x.com/x-api/trends/introduction'],
        ['key' => 'pinterest', 'name' => 'Pinterest API v5 — Trends', 'kind' => 'api', 'geo' => 'US', 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'blocked: needs a business account', 'docs_url' => 'https://developers.pinterest.com/docs/api/v5/'],
        ['key' => 'meta_ad_library', 'name' => 'Meta Ad Library API', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'disabled: outside the EU only politics and social-issue ads are returned', 'docs_url' => 'https://developers.facebook.com/docs/graph-api/reference/ads_archive/'],
    ],
];
