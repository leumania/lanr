<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_tesoreria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('solicitado_por')->constrained('users');
            $table->text('motivo')->nullable();
            $table->decimal('monto', 12, 2);
            $table->string('estado')->default('Pendiente');
            $table->string('origen')->nullable();
            $table->timestamp('fecha_solicitud')->nullable();
            $table->timestamp('fecha_atencion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_tesoreria');
    }
};