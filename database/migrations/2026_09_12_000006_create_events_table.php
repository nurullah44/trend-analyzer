<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('alarm_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('from_state')->nullable();
            $table->string('to_state')->nullable();
            $table->text('reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['type', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
