<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('query');
            $table->string('state')->default('backlog');
            $table->boolean('seasonal')->default(false);
            $table->string('magnitude')->nullable();
            $table->date('first_seen_on');
            $table->date('mainstream_on')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->timestamps();

            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
