<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('cargo')->nullable()->after('username'); // ej. "Gerencia de Obra"
            $table->string('phone')->nullable()->after('email');
            $table->boolean('must_change_password')->default(true)->after('phone');
            $table->boolean('active')->default(true)->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'cargo', 'phone', 'must_change_password', 'active']);
        });
    }
};