<?php

namespace Tests\Feature\Collection\Sources;

use App\Collection\Sources\AppStoreSearch;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\Fixtures;
use Tests\TestCase;

class AppStoreSearchTest extends TestCase
{
    public function test_it_keeps_the_top_apps_metadata_and_store_links_in_store_order(): void
    {
        Http::fake(['itunes.apple.com/search*' => Http::response(Fixtures::json('AppStoreSearch/pdf-scanner.json'))]);

        $competition = $this->app->make(AppStoreSearch::class)->competition('pdf scanner');

        $this->assertSame('app_store_search', $competition['source']);
        $this->assertSame(now('UTC')->toDateString(), $competition['observed_on'], 'Evidence carries when it was observed');
        $this->assertSame('US', $competition['storefront']);
        $this->assertSame(9, $competition['result_count']);
        $this->assertSame([
            'name' => 'Adobe Scan: PDF & Doc Scanner',
            'seller' => 'Adobe Inc.',
            'url' => 'https://apps.apple.com/us/app/adobe-scan-pdf-doc-scanner/id1199564834?uo=4',
            'genre' => 'Business',
            'ratings' => 1596462,
            'rating' => 4.88,
            'released' => '2017-05-31',
            'updated' => '2026-09-29',
            'price' => 'Free',
        ], $competition['apps'][0]);
        $this->assertSame(1913, $competition['apps'][2]['ratings'], 'a young app with few ratings among the giants');

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://itunes.apple.com/search')
            && $request['term'] === 'pdf scanner' && $request['country'] === 'US'
            && $request['media'] === 'software' && $request['entity'] === 'software' && (int) $request['limit'] === 10);
    }

    public function test_calls_are_spaced_and_a_query_is_answered_once_a_period(): void
    {
        Http::fake(['itunes.apple.com/search*' => Http::response(Fixtures::json('AppStoreSearch/pdf-scanner.json'))]);
        $search = $this->app->make(AppStoreSearch::class);

        $search->competition('pdf scanner');
        $search->competition('PDF Scanner ');
        $search->competition('plant identifier');

        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }
}
