<?php

use App\Models\Notificacion;
use App\Models\Obra;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Support\Carbon;

test('opening a notification marks it read and redirects to its tramite', function () {
    $obra = Obra::create(['nombre' => 'N', 'codigo' => 'NOTIF', 'activa' => true]);
    $user = User::factory()->create(['obra_activa_id' => $obra->id]);
    $tramite = Tramite::create(['obra_id' => $obra->id, 'tracking' => 'REQ-NOTIF-1', 'tipo' => 'REQ', 'numero' => '1-2026', 'fecha' => Carbon::today(), 'proyecto' => 'P', 'lugar' => 'L', 'estado' => 'Cerrado', 'creador_id' => $user->id]);
    $notificacion = Notificacion::create(['usuario_id' => $user->id, 'tramite_id' => $tramite->id, 'titulo' => 'x', 'mensaje' => 'y', 'tipo' => 'informativa', 'leida' => false]);

    $this->actingAs($user)
        ->get(route('notificaciones.abrir', $notificacion))
        ->assertRedirect(route('tramites.show', $tramite));

    expect($notificacion->fresh()->leida)->toBeTrue();
});

test('a user cannot open another users notification', function () {
    $obra = Obra::create(['nombre' => 'N2', 'codigo' => 'NOTIF2', 'activa' => true]);
    $owner = User::factory()->create(['obra_activa_id' => $obra->id]);
    $intruder = User::factory()->create(['obra_activa_id' => $obra->id]);
    $notificacion = Notificacion::create(['usuario_id' => $owner->id, 'tramite_id' => null, 'titulo' => 'x', 'mensaje' => 'y', 'tipo' => 'informativa', 'leida' => false]);

    $this->actingAs($intruder)
        ->get(route('notificaciones.abrir', $notificacion))
        ->assertNotFound();
});
