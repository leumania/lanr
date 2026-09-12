<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            $table->string('prioridad')->default('Normal')->after('observaciones');
            $table->date('fecha_requerida')->nullable()->after('prioridad');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('prioridad')->default('Normal')->after('justificacion');
            $table->date('fecha_requerida')->nullable()->after('prioridad');
            $table->string('item_key')->nullable()->after('fecha_requerida');
            $table->unsignedInteger('nro_despacho')->nullable()->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['prioridad', 'fecha_requerida', 'item_key', 'nro_despacho']);
        });

        Schema::table('tramites', function (Blueprint $table) {
            $table->dropColumn(['prioridad', 'fecha_requerida']);
        });
    }
};