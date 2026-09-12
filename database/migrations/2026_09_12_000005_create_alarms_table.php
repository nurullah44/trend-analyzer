<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alarms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->date('published_on');
            $table->string('state_at_publication');
            $table->unsignedInteger('volume')->default(0);
            $table->float('velocity')->default(0);
            $table->unsignedInteger('corroboration')->default(0);
            $table->float('score')->default(0);
            $table->json('evidence');
            $table->string('verdict')->nullable();
            $table->text('verdict_note')->nullable();
            $table->date('closed_on')->nullable();
            $table->timestamps();

            $table->index('published_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarms');
    }
};
