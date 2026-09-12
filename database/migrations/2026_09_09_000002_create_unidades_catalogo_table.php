<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades_catalogo', function (Blueprint $table) {
            $table->id();
            $table->string('abreviatura', 30);
            $table->string('uso', 80);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['abreviatura', 'uso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades_catalogo');
    }
};