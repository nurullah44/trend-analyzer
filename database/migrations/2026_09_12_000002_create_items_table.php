<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->string('url', 2048)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->date('observed_on');
            $table->unsignedInteger('signal')->default(0);
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['observed_on', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
