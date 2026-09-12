<?php

use App\Models\GestionLogistica;
use App\Models\Obra;
use App\Models\Regularizacion;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('enviarAObra exige evidencia fotografica y crea regularizacion si la guia queda pendiente', function () {
    Storage::fake('public');
    Role::findOrCreate('Logística', 'web');
    Role::findOrCreate('Gerencia de Obra', 'web');
    Role::findOrCreate('Control y Planeamiento', 'web');

    $obra = Obra::create(['nombre' => 'Disp', 'codigo' => 'DISP', 'activa' => true]);
    $logistica = User::factory()->create(['obra_activa_id' => $obra->id]);
    $logistica->assignRole('Logística');

    $tramite = Tramite::create(['obra_id' => $obra->id, 'tracking' => 'REQ-DISP-1', 'tipo' => 'REQ', 'numero' => '1-2026', 'fecha' => Carbon::today(), 'proyecto' => 'P', 'lugar' => 'L', 'estado' => 'En gestión de compra', 'creador_id' => $logistica->id]);
    GestionLogistica::create(['tramite_id' => $tramite->id, 'monto' => 100, 'estado_pago' => 'Pagado por Logística']);

    $this->actingAs($logistica);

    // Sin evidencia fotografica -> error
    Livewire::test('tramites.request-detail', ['tramite' => $tramite])
        ->set('envio.guia_opcion', 'pendiente')
        ->call('enviarAObra')
        ->assertHasErrors(['evidencias_envio']);

    expect($tramite->fresh()->estado)->toBe('En gestión de compra');

    // Con evidencia y guia pendiente -> crea Regularizacion 'Guía de remisión'
    Livewire::test('tramites.request-detail', ['tramite' => $tramite])
        ->set('envio.guia_opcion', 'pendiente')
        ->set('evidencias_envio', [UploadedFile::fake()->image('evidencia.jpg')])
        ->call('enviarAObra')
        ->assertHasNoErrors();

    $tramite->refresh();
    expect($tramite->estado)->toBe('Enviado a obra');
    expect($tramite->gestionLogistica->fresh()->guia_pendiente)->toBeTrue();
    expect(Regularizacion::where('tramite_id', $tramite->id)->where('tipo', 'Guía de remisión')->where('estado', 'Pendiente')->exists())->toBeTrue();

    // Reenviar ya no debe ser posible
    Livewire::test('tramites.request-detail', ['tramite' => $tramite->fresh()])
        ->set('envio.guia_opcion', 'pendiente')
        ->set('evidencias_envio', [UploadedFile::fake()->image('evidencia2.jpg')])
        ->call('enviarAObra');

    expect(GestionLogistica::where('tramite_id', $tramite->id)->count())->toBe(1);
});
