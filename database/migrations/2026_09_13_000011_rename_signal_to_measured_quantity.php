<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // The glossary's word for the number a Source reports for an Item.
            $table->renameColumn('signal', 'measured_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->renameColumn('measured_quantity', 'signal');
        });
    }
};
