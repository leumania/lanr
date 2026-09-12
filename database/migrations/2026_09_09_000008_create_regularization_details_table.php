<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regularizacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regularizacion_id')->constrained('regularizaciones')->cascadeOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('precio_total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regularizacion_detalles');
    }
};