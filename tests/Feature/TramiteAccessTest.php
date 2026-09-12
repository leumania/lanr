<?php

use App\Models\Obra;
use App\Models\Tramite;
use App\Models\User;

function makeTramiteForAccessTest(Obra $obra, User $creator, string $estado = 'En proceso'): Tramite
{
    return Tramite::create([
        'obra_id' => $obra->id,
        'tracking' => fake()->unique()->bothify('REQ-????-####'),
        'tipo' => 'REQ',
        'numero' => fake()->numerify('###'),
        'fecha' => now()->toDateString(),
        'proyecto' => 'Proyecto de prueba',
        'lugar' => 'Lugar de prueba',
        'estado' => $estado,
        'creador_id' => $creator->id,
    ]);
}

test('users can only view trámites from their active work', function () {
    $obraActual = Obra::create(['nombre' => 'Obra actual', 'codigo' => 'ACTUAL', 'activa' => true]);
    $otraObra = Obra::create(['nombre' => 'Otra obra', 'codigo' => 'OTRA', 'activa' => true]);
    $user = User::factory()->create(['obra_activa_id' => $obraActual->id]);
    $tramite = makeTramiteForAccessTest($otraObra, $user);

    $this->actingAs($user)->get(route('tramites.show', $tramite))->assertNotFound();
});

test('a trámite pending creator review is private to its creator', function () {
    $obra = Obra::create(['nombre' => 'Obra de prueba', 'codigo' => 'PRUEBA', 'activa' => true]);
    $creator = User::factory()->create(['obra_activa_id' => $obra->id]);
    $reviewer = User::factory()->create(['obra_activa_id' => $obra->id]);
    $tramite = makeTramiteForAccessTest($obra, $creator, 'Pendiente de mi revisión');

    $this->actingAs($reviewer)->get(route('tramites.show', $tramite))->assertNotFound();
    $this->actingAs($creator)->get(route('tramites.show', $tramite))->assertOk();
});