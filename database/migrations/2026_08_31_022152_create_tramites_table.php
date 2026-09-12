<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramites', function (Blueprint $table) {
            $table->id();
            $table->string('tracking')->unique(); // REQ-LAVEGA-2026-001
            $table->string('tipo'); // REQ o SP
            $table->string('subtipo')->nullable();
            $table->string('numero');
            $table->date('fecha');
            $table->text('proyecto');
            $table->text('lugar');
            $table->string('beneficiario')->nullable();
            $table->string('dni_ruc')->nullable();
            $table->string('modalidad_pago')->nullable(); // Planilla / Persona-Empresa
            $table->string('responsable')->nullable();
            $table->string('tipo_comprobante')->nullable();
            $table->string('banco')->nullable();
            $table->string('nro_comprobante')->nullable();
            $table->string('cuenta_cci')->nullable();
            $table->decimal('abono', 12, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado'); // Pendiente de aprobación, En oficina, etc.
            $table->string('formato')->nullable(); // F01A-LANR-XX
            $table->foreignId('creador_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramites');
    }
};