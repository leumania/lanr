<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestion_sp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->unique()->constrained('tramites')->cascadeOnDelete();

            $table->string('asignado_pago')->nullable();
            $table->foreignId('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_asignacion')->nullable();

            $table->foreignId('pagado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('medio_pago')->nullable();
            $table->string('banco_pago')->nullable();
            $table->string('nro_operacion')->nullable();
            $table->decimal('monto_pagado', 12, 2)->nullable();
            $table->timestamp('fecha_pago')->nullable();

            $table->string('nombre_original_pago')->nullable();
            $table->string('nombre_archivo_pago')->nullable();

            $table->boolean('conformidad_gg')->default(false);
            $table->timestamp('fecha_conformidad')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestion_sp');
    }
};