<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reembolsos_rendiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('numero');
            $table->date('fecha');
            $table->foreignId('solicitante_id')->constrained('users');
            $table->text('concepto');
            $table->decimal('monto', 12, 2);
            $table->string('moneda', 10)->default('PEN');
            $table->text('observaciones')->nullable();
            $table->string('estado')->default('Pendiente');
            $table->foreignId('autorizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_autorizacion')->nullable();
            $table->foreignId('atendido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha_atencion')->nullable();
            $table->timestamps();
            $table->index(['obra_id', 'estado']);
        });

        Schema::create('reembolso_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reembolso_id')->constrained('reembolsos_rendiciones')->cascadeOnDelete();
            $table->string('tipo')->default('sustento');
            $table->string('nombre_original');
            $table->string('nombre_archivo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reembolso_adjuntos');
        Schema::dropIfExists('reembolsos_rendiciones');
    }
};