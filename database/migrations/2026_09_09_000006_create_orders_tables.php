<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->string('tipo_orden');
            $table->string('numero');
            $table->date('fecha');
            $table->string('proveedor')->nullable();
            $table->string('documento_proveedor')->nullable();
            $table->string('moneda', 10)->default('PEN');
            $table->text('descripcion');
            $table->decimal('total', 12, 2)->default(0);
            $table->string('estado')->default('Pendiente de aprobación');
            $table->foreignId('creador_id')->constrained('users');
            $table->foreignId('vobo_gerencia_obra')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vobo_administracion')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vobo_gerencia_general')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['obra_id', 'numero']);
        });

        Schema::create('orden_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $table->string('descripcion');
            $table->string('unidad')->nullable();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('monto', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_items');
        Schema::dropIfExists('ordenes');
    }
};