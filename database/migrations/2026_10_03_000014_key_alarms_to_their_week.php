<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alarms', function (Blueprint $table) {
            // The Monday of the scored week that published the Alarm: the weekly report reads by it.
            $table->string('week', 10)->default('')->after('subject_id');
            $table->index('week');
        });
    }

    public function down(): void
    {
        Schema::table('alarms', function (Blueprint $table) {
            $table->dropIndex(['week']);
            $table->dropColumn('week');
        });
    }
};
