<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despachos_logistica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('medio_envio');
            $table->string('responsable_transporte')->nullable();
            $table->decimal('costo_envio', 12, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->string('guia_numero')->nullable();
            $table->date('guia_fecha')->nullable();
            $table->boolean('guia_pendiente')->default(false);
            $table->foreignId('creado_por')->constrained('users');
            $table->timestamps();
            $table->index(['tramite_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despachos_logistica');
    }
};
