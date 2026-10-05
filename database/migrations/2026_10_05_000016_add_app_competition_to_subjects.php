<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            // Competition (ADR-0011): the apps App Store search returns for the Subject's query.
            $table->json('app_competition')->nullable();
            $table->date('app_competition_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['app_competition', 'app_competition_on']);
        });
    }
};
