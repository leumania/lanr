<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras_logistica', function (Blueprint $table) {
            $table->foreignId('solicitud_tesoreria_id')->nullable()->after('proveedor_id')
                ->constrained('solicitudes_tesoreria')->nullOnDelete();
            $table->index('solicitud_tesoreria_id');
        });
    }

    public function down(): void
    {
        Schema::table('compras_logistica', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitud_tesoreria_id');
        });
    }
};
