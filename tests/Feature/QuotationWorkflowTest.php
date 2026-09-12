<?php

use App\Models\Obra;
use App\Models\Tramite;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('logistics can open quotation management for a requirement', function () {
    $obra = Obra::create(['nombre' => 'Obra cotizaciones', 'codigo' => 'COT-TEST', 'activa' => true]);
    $user = User::factory()->create(['obra_activa_id' => $obra->id]);
    Role::findOrCreate('Logística', 'web');
    $user->assignRole('Logística');
    $tramite = Tramite::create([
        'obra_id' => $obra->id,
        'tracking' => 'REQ-COT-TEST-001',
        'tipo' => 'REQ',
        'numero' => '001',
        'fecha' => now()->toDateString(),
        'proyecto' => 'Proyecto',
        'lugar' => 'Lugar',
        'estado' => 'Recibido por Logística',
        'creador_id' => $user->id,
    ]);

    $this->actingAs($user)->get(route('tramites.quotations', $tramite))->assertOk();
});