<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

/** Registers the approved Sources without disturbing collection health already recorded. */
class SourceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('trend.sources') as $source) {
            Source::updateOrCreate(
                ['key' => $source['key']],
                [
                    'name' => $source['name'],
                    'kind' => $source['kind'],
                    'geo' => $source['geo'] ?? null,
                    'roles' => $source['roles'] ?? 'discovery',
                    'enabled' => $source['enabled'],
                    'cost_note' => $source['cost_note'] ?? null,
                    'docs_url' => $source['docs_url'] ?? null,
                ]
            );
        }
    }
}
