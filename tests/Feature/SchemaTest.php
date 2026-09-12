<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_analyzer_schema_exists(): void
    {
        foreach (['sources', 'items', 'subjects', 'subject_days', 'alarms', 'events', 'labels', 'label_subject'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing table: {$table}");
        }
    }

    public function test_items_are_unique_per_source_and_external_id(): void
    {
        $this->assertTrue(
            collect(Schema::getIndexes('items'))->contains(
                fn (array $index) => $index['unique'] && $index['columns'] === ['source_id', 'external_id']
            )
        );
    }
}
