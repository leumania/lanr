<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        $obraId = DB::table('obras')->insertGetId([
            'nombre' => 'La Vega',
            'codigo' => 'LAVEGA',
            'descripcion' => 'Obra activa inicial del sistema.',
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('obra_activa_id')->nullable()->after('active')->constrained('obras')->nullOnDelete();
        });

        Schema::table('tramites', function (Blueprint $table) {
            $table->foreignId('obra_id')->nullable()->after('id')->constrained('obras')->nullOnDelete();
        });

        DB::table('users')->update(['obra_activa_id' => $obraId]);
        DB::table('tramites')->update(['obra_id' => $obraId]);
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            $table->dropForeign(['obra_id']);
            $table->dropColumn('obra_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['obra_activa_id']);
            $table->dropColumn('obra_activa_id');
        });

        Schema::dropIfExists('obras');
    }
};
