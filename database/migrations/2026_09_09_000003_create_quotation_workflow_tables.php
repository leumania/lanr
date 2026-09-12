<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('documento', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('nombre');
        });

        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('creado_por')->constrained('users');
            $table->string('tipo_sustento');
            $table->date('fecha');
            $table->string('estado')->default('Borrador');
            $table->text('observacion')->nullable();
            $table->timestamp('enviado_fecha')->nullable();
            $table->timestamps();
            $table->index(['tramite_id', 'estado']);
        });

        Schema::create('cotizacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 12, 2);
            $table->timestamps();
            $table->unique(['cotizacion_id', 'item_id']);
        });

        Schema::create('cotizacion_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('nombre_archivo');
            $table->timestamps();
        });

        Schema::create('autorizaciones_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('autorizado_por')->constrained('users');
            $table->string('estado')->default('Autorizada');
            $table->text('motivo_anulacion')->nullable();
            $table->timestamp('fecha')->nullable();
            $table->timestamp('anulada_fecha')->nullable();
            $table->timestamps();
        });

        Schema::create('autorizacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autorizacion_id')->constrained('autorizaciones_compra')->cascadeOnDelete();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones');
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autorizacion_items');
        Schema::dropIfExists('autorizaciones_compra');
        Schema::dropIfExists('cotizacion_archivos');
        Schema::dropIfExists('cotizacion_items');
        Schema::dropIfExists('cotizaciones');
        Schema::dropIfExists('proveedores');
    }
};