<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->text('accion');
            $table->timestamps(); // created_at reemplaza tu campo 'fecha'
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('history');
    }
};