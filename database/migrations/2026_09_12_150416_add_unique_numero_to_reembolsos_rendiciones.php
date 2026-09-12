<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reembolsos_rendiciones', function (Blueprint $table) {
            $table->unique(['obra_id', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reembolsos_rendiciones', function (Blueprint $table) {
            $table->dropUnique(['obra_id', 'numero']);
        });
    }
};
