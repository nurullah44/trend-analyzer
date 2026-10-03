<?php

namespace App\Providers;

use App\Collection\SourceCollector;
use App\Collection\SourceMeasurement;
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
        // Sources are built from config/trend.php, which mirrors the approved list
        // in docs/sources.md. Adding a Source is one docs entry, one config entry
        // and one class implementing the collection or measurement contract.
        $this->app->singleton(SourceRegistry::class, function (Application $app): SourceRegistry {
            $collectors = $measurements = [];

            foreach (config('trend.sources', []) as $source) {
                if (! isset($source['class'])) {
                    continue;
                }

                $implementation = $app->make($source['class']);

                if (! $implementation instanceof SourceCollector && ! $implementation instanceof SourceMeasurement) {
                    throw new InvalidArgumentException(
                        "The class for Source [{$source['key']}] implements neither the collection nor the measurement contract."
                    );
                }

                if ($implementation instanceof SourceCollector) {
                    $collectors[$source['key']] = $implementation;
                }

                if ($implementation instanceof SourceMeasurement) {
                    $measurements[$source['key']] = $implementation;
                }
            }

            return new SourceRegistry($collectors, $measurements);
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
