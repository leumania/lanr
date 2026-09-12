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
        Schema::table('gestion_logistica', function (Blueprint $table) {
            $table->string('medio_envio')->nullable()->after('guia_pendiente');
            $table->string('responsable_transporte')->nullable()->after('medio_envio');
            $table->decimal('costo_envio', 12, 2)->nullable()->after('responsable_transporte');
            $table->text('observacion_envio')->nullable()->after('costo_envio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gestion_logistica', function (Blueprint $table) {
            $table->dropColumn(['medio_envio', 'responsable_transporte', 'costo_envio', 'observacion_envio']);
        });
    }
};
