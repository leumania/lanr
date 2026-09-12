<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->string('seccion'); // Ejecución de Obra / Ing. de Seguridad
            $table->integer('nro');
            $table->text('descripcion');
            $table->string('unidad');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('stock', 12, 2)->default(0);
            $table->decimal('comprar', 12, 2)->default(0); // calculado: max(cantidad-stock,0)
            $table->text('justificacion')->nullable();
            $table->decimal('costo', 12, 2)->nullable();
            $table->decimal('monto', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};