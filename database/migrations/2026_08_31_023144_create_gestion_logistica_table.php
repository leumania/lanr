<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestion_logistica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->unique()->constrained('tramites')->cascadeOnDelete();
            $table->timestamp('recibido_fecha')->nullable();

            $table->string('cotizacion_estado')->default('Pendiente');

            $table->string('proveedor')->nullable();
            $table->string('ruc')->nullable();
            $table->date('fecha_compra')->nullable();
            $table->string('tipo_comprobante')->nullable();
            $table->string('nro_comprobante')->nullable();
            $table->decimal('monto', 12, 2)->default(0);

            $table->boolean('comprobante_pendiente')->default(false);

            $table->string('forma_pago')->nullable();
            $table->string('estado_pago')->nullable();

            $table->string('guia_numero')->nullable();
            $table->date('guia_fecha')->nullable();
            $table->boolean('guia_pendiente')->default(false);

            $table->timestamp('enviado_fecha')->nullable();
            $table->boolean('requiere_reembolso')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestion_logistica');
    }
};