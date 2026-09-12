<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_logistica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->date('fecha_compra');
            $table->string('tipo_comprobante');
            $table->string('nro_comprobante')->nullable();
            $table->decimal('monto', 12, 2);
            $table->string('nombre_original')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->foreignId('creado_por')->constrained('users');
            $table->timestamps();
            $table->index(['tramite_id', 'fecha_compra']);
        });

        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras_logistica')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('cantidad_comprada', 12, 2);
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
        Schema::dropIfExists('compras_logistica');
    }
};