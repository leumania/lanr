<?php

use App\Livewire\Tramites\RequestList;
use App\Models\GestionLogistica;
use App\Models\Notificacion;
use App\Models\Obra;
use App\Models\SolicitudTesoreria;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('caja logistica bloqueada sobre el umbral y pago personal genera solicitud de reembolso', function () {
    Role::findOrCreate('Logística', 'web');
    Role::findOrCreate('Tesorería', 'web');

    $obra = Obra::create(['nombre' => 'LogPay', 'codigo' => 'LOGPAY', 'activa' => true]);
    $logistica = User::factory()->create(['obra_activa_id' => $obra->id]);
    $logistica->assignRole('Logística');
    $tesoreria = User::factory()->create(['obra_activa_id' => $obra->id]);
    $tesoreria->assignRole('Tesorería');

    $tramite = Tramite::create(['obra_id' => $obra->id, 'tracking' => 'REQ-LOGPAY-1', 'tipo' => 'REQ', 'numero' => '1-2026', 'fecha' => Carbon::today(), 'proyecto' => 'P', 'lugar' => 'L', 'estado' => 'En gestión de compra', 'creador_id' => $logistica->id]);
    GestionLogistica::create(['tramite_id' => $tramite->id, 'monto' => 2000]);

    $this->actingAs($logistica);

    Livewire::test(RequestList::class); // warm up nothing, just ensure roles ok

    $component = Livewire::test('tramites.request-detail', ['tramite' => $tramite]);
    $component->call('marcarPagado');
    expect($tramite->gestionLogistica->fresh()->estado_pago)->toBeNull();

    $component->call('registrarPagoPersonal');
    expect($tramite->gestionLogistica->fresh()->estado_pago)->toBeNull();

    $tramite->gestionLogistica->update(['monto' => 500]);
    $component2 = Livewire::test('tramites.request-detail', ['tramite' => $tramite->fresh()]);
    $component2->call('registrarPagoPersonal');

    $gestion = $tramite->gestionLogistica->fresh();
    expect($gestion->estado_pago)->toBe('Pagado por Logística')
        ->and($gestion->forma_pago)->toBe('Pago personal')
        ->and($gestion->requiere_reembolso)->toBeTrue();

    $solicitud = SolicitudTesoreria::where('tramite_id', $tramite->id)->where('origen', 'REQ-Reembolso')->first();
    expect($solicitud)->not->toBeNull()
        ->and($solicitud->estado)->toBe('Pendiente')
        ->and((float) $solicitud->monto)->toBe(500.0);

    expect(Notificacion::where('usuario_id', $tesoreria->id)->count())->toBeGreaterThan(0);
});
