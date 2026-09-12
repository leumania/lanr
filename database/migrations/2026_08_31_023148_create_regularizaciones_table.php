<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regularizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('responsable_id')->constrained('users');
            $table->string('tipo');
            $table->text('descripcion')->nullable();
            $table->string('estado')->default('Pendiente');
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_regularizacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regularizaciones');
    }
};