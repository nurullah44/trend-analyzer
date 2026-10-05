<?php

use App\Collection\Sources\AppleAds;
use App\Collection\Sources\HackerNews;
use App\Collection\Sources\StackExchange;
use App\Collection\Sources\Wikimedia;

/*
| The approved Sources, mirroring docs/sources.md. Adding a Source means one
| entry here plus one class implementing the collection or measurement contract.
|
| `roles` says what job the Source does (ADR-0004, ADR-0005):
|   discovery   — proposes Candidates from what was published on a day
|   measurement — counts one Subject's matches per week, for any past week
|   marker      — one of the Subject's own measures says it has arrived
|   validation  — sizes a Subject we already track
|
| The analyzer watches subjects globally. A few Sources are only readable one
| country at a time — those carry a `geo`; Sources that are global by nature
| leave it null. `enabled` false means allowed but deliberately not collected.
*/
return [
    // The fallback for Sources that can only be read one country at a time.
    // Global or the United States only — Türkiye is never used as a geo.
    'default_geo' => env('TREND_GEO', 'US'),

    // Candidates from a day's Items (ADR-0006): a name needs this many mentions,
    // and at most this many are classified a day.
    'discovery' => [
        'min_mentions' => 3,
        'max_candidates' => 30,
    ],

    // The Classifier: TypeSafe's Jev. Without a key every Candidate waits in Backlog.
    'classifier' => [
        'key' => env('JEV_API_KEY'),
        'url' => env('JEV_URL', 'https://api.typesafe.ai'),
        'model' => env('JEV_MODEL', 'jev-latest'),
        'track_at' => 0.8,   // at or above: Watching
        'backlog_at' => 0.5, // at or above: Backlog; below: dropped
        'labels' => [
            'ai' => 'An AI model, AI product or AI technique',
            'dev-tool' => 'A tool, library, framework or service for software developers',
            'mobile-app' => 'A mobile app or something people do on their phones',
            'web-app' => 'A web product or online service for end users',
            'utility-need' => 'A practical everyday problem or need people want solved',
            'other' => 'None of the above',
        ],
    ],

    // Optional Source keys that only raise quotas.
    'keys' => [
        'stack_exchange' => env('STACK_EXCHANGE_KEY'),
    ],

    // The weekly score (ADR-0004). A crude placeholder until Verdicts and the backtest tune it.
    'scoring' => [
        'baseline_weeks' => 8,          // the trailing weeks a Velocity is measured against
        'min_baseline_weeks' => 4,      // fewer measured weeks is a cold start: no Velocity
        'rising_velocity' => 2.0,       // a Source counts towards Corroboration at or above this…
        'min_volume' => 3,              // …and with at least this Volume in the week
        'velocity_cap' => 5.0,          // one loud Source adds at most this to the Trend Score
        'rising_score' => 2.0,          // Watching → Rising
        'trending_score' => 5.0,        // → Trending, together with…
        'trending_corroboration' => 2,  // …at least this many Sources rising
        'archive_after_days' => 30,     // Watching this long without rising → Archived
    ],

    // A Subject has arrived when one of its own measures holds over a line (ADR-0005).
    'mainstream' => [
        'wikipedia_weekly_views' => 70_000, // two consecutive weeks at or above
        'monthly_searches' => 100_000,      // the last two Google Ads months at or above
    ],

    // Google Ads keyword metrics, fetched for Rising and Trending Subjects at most once in this many days.
    'google_ads' => [
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
        'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
        'currency' => env('GOOGLE_ADS_CURRENCY', 'TRY'),
        'refresh_after_days' => 28,
    ],

    // Apple Ads search-term popularity (ADR-0011): App Store demand, read for one storefront.
    // Each publication Monday the top terms of every genre are read; a term new to them
    // (absent from twice the depth the week before) or climbing at least `min_climb`
    // places is a Candidate, the biggest climbs first, at most `max_candidates` a week.
    'apple_ads' => [
        'client_id' => env('APPLE_ADS_CLIENT_ID'),
        'team_id' => env('APPLE_ADS_TEAM_ID'),
        'key_id' => env('APPLE_ADS_KEY_ID'),
        'ad_account_id' => env('APPLE_ADS_AD_ACCOUNT_ID'),
        'private_key_path' => env('APPLE_ADS_PRIVATE_KEY_PATH'),
        'storefront' => 'US',
        'top_terms' => 500,
        'min_climb' => 100,
        'max_candidates' => 20,
    ],

    // Competition from the iTunes Search API (ADR-0011): the top apps App Store search returns
    // for a Rising or Trending Subject's query, fetched at most once in this many days.
    'app_store_search' => [
        'storefront' => 'US',
        'top_apps' => 10,
        'refresh_after_days' => 28,
    ],

    // Cases for trends:backtest: breakouts the score should catch before their marker,
    // and names that never broke out. Curate this list; it is the instrument's report card.
    'backtest' => [
        'DeepSeek', 'Model Context Protocol', 'Vibe coding', 'Ollama', 'Bluesky',
        'Rabbit R1', 'Humane Ai Pin', 'Threads',
    ],

    'sources' => [
        // Demand: people asking for help, by category.
        ['key' => 'stack_exchange', 'name' => 'Stack Exchange API', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery,measurement', 'enabled' => true, 'cost_note' => 'free, no key needed at this volume', 'docs_url' => 'https://api.stackexchange.com/docs', 'class' => StackExchange::class],
        ['key' => 'hacker_news', 'name' => 'Hacker News (Algolia)', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery,measurement', 'enabled' => true, 'cost_note' => 'free', 'docs_url' => 'https://hn.algolia.com/api', 'class' => HackerNews::class],

        // App Store demand (ADR-0011): what people type into App Store search, per genre, weekly.
        ['key' => 'apple_ads', 'name' => 'Apple Ads search-term popularity', 'kind' => 'api', 'geo' => 'US', 'roles' => 'discovery,measurement', 'enabled' => true, 'cost_note' => 'free; needs the Apple Ads API user\'s credentials and private key; weekly, 65 weeks kept', 'docs_url' => 'https://developer.apple.com/documentation/apple-ads-platform-api/query-app-search-term-popularity-data', 'class' => AppleAds::class],

        ['key' => 'app_store_search', 'name' => 'iTunes Search API — App Store search results', 'kind' => 'api', 'geo' => 'US', 'roles' => 'validation', 'enabled' => true, 'cost_note' => 'free, no key; about 20 calls a minute; Competition for Rising and Trending Subjects, metadata only', 'docs_url' => 'https://performance-partners.apple.com/search-api'],

        // Supply: what is actually launching and being adopted. Deferred until after the foundation.
        ['key' => 'product_hunt', 'name' => 'Product Hunt GraphQL API', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'deferred until after the foundation; free; needs a developer token', 'docs_url' => 'https://api.producthunt.com/v2/docs'],
        ['key' => 'apple_chart', 'name' => 'Apple Marketing Tools — top free apps by genre', 'kind' => 'rss', 'geo' => 'US', 'roles' => 'discovery,marker', 'enabled' => false, 'cost_note' => 'deferred until after the foundation; free; read per genre rather than as one top-100 list', 'docs_url' => 'https://rss.applemarketingtools.com/'],

        // Interest, but only ever read through a category filter. Deferred until after the foundation.
        ['key' => 'youtube_trending', 'name' => 'YouTube Data API — trending by category (28 Science & Technology, 27 Education)', 'kind' => 'api', 'geo' => 'US', 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'deferred until after the foundation; free, 10k units/day; needs a Google Cloud API key; read with videoCategoryId, never the unfiltered chart', 'docs_url' => 'https://developers.google.com/youtube/v3/docs/videos/list'],

        // Measurement, and a Mainstream marker through its weekly views (ADR-0005).
        ['key' => 'wikimedia', 'name' => 'Wikimedia Pageviews API', 'kind' => 'api', 'geo' => null, 'roles' => 'measurement,marker', 'enabled' => true, 'cost_note' => 'free; CC BY-SA attribution', 'docs_url' => 'https://wikimedia.org/api/rest_v1/', 'class' => Wikimedia::class],

        // Deferred: per-Subject thresholds replaced chart markers (ADR-0005).
        ['key' => 'google_trends', 'name' => 'Google Trends trending searches', 'kind' => 'rss', 'geo' => 'GLOBAL', 'roles' => 'marker', 'enabled' => false, 'cost_note' => 'deferred until after the foundation; free', 'docs_url' => 'https://trends.google.com/trending/rss?geo=GLOBAL'],

        // Validation and a Mainstream marker, read only for Rising and Trending Subjects.
        ['key' => 'google_ads', 'name' => 'Google Ads keyword metrics', 'kind' => 'api', 'geo' => null, 'roles' => 'validation,marker', 'enabled' => true, 'cost_note' => 'free per call; Basic access granted to the owner\'s Cloud project; bids in the account currency', 'docs_url' => 'https://developers.google.com/google-ads/api/rest/reference/rest/v25/customers/generateKeywordHistoricalMetrics'],

        // Parked or blocked, kept registered so the reason is visible.
        ['key' => 'google_news', 'name' => 'Google News RSS (topic feeds)', 'kind' => 'rss', 'geo' => 'GLOBAL', 'roles' => 'marker', 'enabled' => false, 'cost_note' => 'disabled: attention and news, not demand', 'docs_url' => 'https://news.google.com/rss/headlines/section/topic/TECHNOLOGY?hl=en-US&gl=US&ceid=US:en'],
        ['key' => 'gdelt', 'name' => 'GDELT DOC 2.0', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'disabled: news volume measures attention, not demand', 'docs_url' => 'https://api.gdeltproject.org/api/v2/doc/doc'],
        ['key' => 'reddit', 'name' => 'Reddit Data API', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'blocked: approval and a registered developer profile required; access request pending', 'docs_url' => 'https://redditinc.com/policies/responsible-builder-policy'],
        ['key' => 'x_trends', 'name' => 'X API', 'kind' => 'api', 'geo' => 'GLOBAL', 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'parked: algorithmic, paid per post, or names-only; topic feeds have no API', 'docs_url' => 'https://docs.x.com/x-api/trends/introduction'],
        ['key' => 'pinterest', 'name' => 'Pinterest API v5 — Trends', 'kind' => 'api', 'geo' => 'US', 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'blocked: needs a business account', 'docs_url' => 'https://developers.pinterest.com/docs/api/v5/'],
        ['key' => 'meta_ad_library', 'name' => 'Meta Ad Library API', 'kind' => 'api', 'geo' => null, 'roles' => 'discovery', 'enabled' => false, 'cost_note' => 'disabled: outside the EU only politics and social-issue ads are returned', 'docs_url' => 'https://developers.facebook.com/docs/graph-api/reference/ads_archive/'],
    ],
];
