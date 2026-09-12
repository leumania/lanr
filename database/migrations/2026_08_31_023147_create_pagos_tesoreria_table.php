<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_tesoreria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes_tesoreria')->cascadeOnDelete();
            $table->string('nombre_original')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->string('medio_pago')->nullable();
            $table->string('banco')->nullable();
            $table->decimal('monto', 12, 2)->nullable();
            $table->string('nro_operacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_tesoreria');
    }
};