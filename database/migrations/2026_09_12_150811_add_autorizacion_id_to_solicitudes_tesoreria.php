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
        Schema::table('solicitudes_tesoreria', function (Blueprint $table) {
            $table->foreignId('autorizacion_id')->nullable()->after('tramite_id')
                ->constrained('autorizaciones_compra')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_tesoreria', function (Blueprint $table) {
            $table->dropConstrainedForeignId('autorizacion_id');
        });
    }
};
