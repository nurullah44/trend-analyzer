<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('volume')->default(0);
            $table->unsignedInteger('strength')->default(0);
            $table->timestamps();

            $table->unique(['subject_id', 'source_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_days');
    }
};
