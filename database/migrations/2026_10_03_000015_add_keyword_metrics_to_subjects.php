<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            // Google Ads keyword metrics for validation and the Mainstream marker, with the account currency.
            $table->json('keyword_metrics')->nullable();
            $table->date('keyword_metrics_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['keyword_metrics', 'keyword_metrics_on']);
        });
    }
};
