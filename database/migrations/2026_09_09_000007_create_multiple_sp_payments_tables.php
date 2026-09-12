<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_modalidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->string('nombre');
            $table->decimal('monto', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('sp_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->string('banco')->nullable();
            $table->string('cuenta_cci')->nullable();
            $table->timestamps();
        });

        Schema::create('sp_comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->string('tipo')->nullable();
            $table->string('numero')->nullable();
            $table->decimal('monto', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('sp_pagos_multiples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_sp_id')->constrained('gestion_sp')->cascadeOnDelete();
            $table->foreignId('pagado_por')->constrained('users');
            $table->string('medio_pago');
            $table->string('banco')->nullable();
            $table->string('nro_operacion')->nullable();
            $table->decimal('monto', 12, 2);
            $table->date('fecha_pago');
            $table->string('nombre_original')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->timestamps();
        });

        Schema::create('archivos_pago_sp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gestion_sp_id')->constrained('gestion_sp')->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('nombre_archivo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_pago_sp');
        Schema::dropIfExists('sp_pagos_multiples');
        Schema::dropIfExists('sp_comprobantes');
        Schema::dropIfExists('sp_cuentas');
        Schema::dropIfExists('sp_modalidades');
    }
};