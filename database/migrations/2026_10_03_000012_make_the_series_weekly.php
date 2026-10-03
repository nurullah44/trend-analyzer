<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The series is weekly and measured by query (ADR-0004); it held only dummy rows before. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('subject_days');

        Schema::create('subject_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('week', 10); // the week's Monday, Y-m-d, UTC
            $table->string('query'); // what was asked: a Subject whose query changes starts a new series
            $table->unsignedInteger('volume')->nullable();
            $table->timestamps();

            $table->unique(['subject_id', 'source_id', 'query', 'week']);
        });
    }

    public function down(): void
    {
        Schema::drop('subject_weeks');

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
};
