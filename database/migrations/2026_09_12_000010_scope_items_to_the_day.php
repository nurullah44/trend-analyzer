<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // A question's score can be negative: the measured quantity is signed.
            $table->integer('signal')->default(0)->change();

            // An Item belongs to the day it was published: a Source that keeps
            // reporting the same thing across days keeps one Item per day, and a
            // re-run of one day replaces in place.
            $table->dropUnique(['source_id', 'external_id']);
            $table->unique(['source_id', 'observed_on', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('signal')->default(0)->change();
            $table->dropUnique(['source_id', 'observed_on', 'external_id']);
        });

        // The identity being restored kept one Item per Source per external id, so
        // collapse the days first — last row written wins — or the old index cannot
        // be rebuilt over the duplicates this migration made legal.
        DB::table('items')->whereNotIn('id', function ($query) {
            $query->selectRaw('max(id)')->from('items')->groupBy('source_id', 'external_id');
        })->delete();

        Schema::table('items', function (Blueprint $table) {
            $table->unique(['source_id', 'external_id']);
        });
    }
};
