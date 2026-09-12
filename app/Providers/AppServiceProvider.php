<?php

namespace App\Providers;

use App\Collection\SourceCollector;
use App\Collection\SourceRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Collectors are built from the approved list, so adding a Source is one
        // config entry plus one implementation of the contract — nothing else.
        $this->app->singleton(SourceRegistry::class, function (Application $app): SourceRegistry {
            $collectors = [];

            foreach (config('trend.sources', []) as $source) {
                if (! isset($source['collector'])) {
                    continue;
                }

                $collector = $app->make($source['collector']);

                if (! $collector instanceof SourceCollector) {
                    throw new InvalidArgumentException(
                        "The collector for Source [{$source['key']}] does not implement the Source contract."
                    );
                }

                $collectors[$source['key']] = $collector;
            }

            return new SourceRegistry($collectors);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
