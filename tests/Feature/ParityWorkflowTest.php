<?php

use App\Livewire\Admin\Orders;
use App\Livewire\Admin\Treasury;
use App\Livewire\Obras\Selector;
use App\Models\GestionLogistica;
use App\Models\Obra;
use App\Models\Orden;
use App\Models\Reembolso;
use App\Models\SolicitudTesoreria;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function parityRole(string $role): void
{
    Role::findOrCreate($role, 'web');
}

test('creator can edit a pending requirement without changing its workflow state', function () {
    parityRole('Gerencia de Obra');
    $obra = Obra::create(['nombre' => 'Edición', 'codigo' => 'EDIT-PAR', 'activa' => true]);
    $user = User::factory()->create(['obra_activa_id' => $obra->id]);
    $user->assignRole('Gerencia de Obra');
    $tramite = Tramite::create(['obra_id' => $obra->id, 'tracking' => 'REQ-EDIT-001', 'tipo' => 'REQ', 'numero' => '1-2026', 'fecha' => Carbon::today(), 'proyecto' => 'P', 'lugar' => 'L', 'estado' => 'Pendiente de mi revisión', 'creador_id' => $user->id]);
    $tramite->items()->create(['seccion' => 'Ejecución de Obra', 'nro' => 1, 'descripcion' => 'Anterior', 'unidad' => 'UND', 'cantidad' => 1, 'stock' => 0, 'comprar' => 1]);

    $this->actingAs($user);
    Livewire::test('tramites.edit-request', ['tramite' => $tramite])
        ->set('numero', '2-2026')
        ->set('items.0.descripcion', 'Actualizado')
        ->call('save')
        ->assertHasNoErrors();

    expect($tramite->fresh()->numero)->toBe('2-2026')
        ->and($tramite->fresh()->items->first()->descripcion)->toBe('Actualizado')
        ->and($tramite->fresh()->estado)->toBe('Pendiente de mi revisión');
});

test('obra selector rejects an unassigned active work', function () {
    $obraActual = Obra::create(['nombre' => 'Asignada', 'codigo' => 'ASSIGN-A', 'activa' => true]);
    $otraObra = Obra::create(['nombre' => 'No asignada', 'codigo' => 'ASSIGN-B', 'activa' => true]);
    $user = User::factory()->create(['obra_activa_id' => $obraActual->id]);

    $this->actingAs($user);
    expect(fn () => Livewire::test(Selector::class)->set('obraId', $otraObra->id))
        ->toThrow(ModelNotFoundException::class);
});

test('treasury pays a pending purchase request with exact amount and evidence', function () {
    parityRole('Tesorería');
    Storage::fake('public');
    $obra = Obra::create(['nombre' => 'Tesorería', 'codigo' => 'TRE-PAR', 'activa' => true]);
    $treasury = User::factory()->create(['obra_activa_id' => $obra->id]);
    $treasury->assignRole('Tesorería');
    $tramite = Tramite::create(['obra_id' => $obra->id, 'tracking' => 'REQ-TRE-001', 'tipo' => 'REQ', 'numero' => '1', 'fecha' => Carbon::today(), 'proyecto' => 'P', 'lugar' => 'L', 'estado' => 'En gestión de compra', 'creador_id' => $treasury->id]);
    GestionLogistica::create(['tramite_id' => $tramite->id, 'monto' => 50, 'estado_pago' => 'Pendiente de Tesorería']);
    $solicitud = SolicitudTesoreria::create(['tramite_id' => $tramite->id, 'solicitado_por' => $treasury->id, 'motivo' => 'Compra', 'monto' => 50, 'estado' => 'Pendiente', 'origen' => 'REQ-Compra', 'fecha_solicitud' => now()]);

    $this->actingAs($treasury);
    Livewire::test(Treasury::class)
        ->set('monto', '50')
        ->set('comprobante', UploadedFile::fake()->create('pago.pdf', 10, 'application/pdf'))
        ->call('pagarSolicitud', $solicitud->id)
        ->assertHasNoErrors();

    expect($solicitud->fresh()->estado)->toBe('Atendida')
        ->and($tramite->gestionLogistica->fresh()->estado_pago)->toBe('Pagado por Tesorería');
});

test('orders persist item detail and reconcile the total', function () {
    parityRole('Administración');
    $obra = Obra::create(['nombre' => 'Orden', 'codigo' => 'ORD-PAR', 'activa' => true]);
    $admin = User::factory()->create(['obra_activa_id' => $obra->id]);
    $admin->assignRole('Administración');

    $this->actingAs($admin);
    Livewire::test(Orders::class)
        ->set('numero', 'ORD-PAR-001')
        ->set('proveedor', 'Proveedor')
        ->set('descripcion', 'Compra')
        ->set('items.0.descripcion', 'Material')
        ->set('items.0.unidad', 'UND')
        ->set('items.0.cantidad', 2)
        ->set('items.0.precio_unitario', 25)
        ->set('total', '50')
        ->call('save')
        ->assertHasNoErrors();

    expect(Orden::firstOrFail()->items)->toHaveCount(1);
});

test('reimbursement attendance requires evidence', function () {
    parityRole('Tesorería');
    $obra = Obra::create(['nombre' => 'Reembolso', 'codigo' => 'RMB-PAR', 'activa' => true]);
    $treasury = User::factory()->create(['obra_activa_id' => $obra->id]);
    $treasury->assignRole('Tesorería');
    $reembolso = Reembolso::create(['obra_id' => $obra->id, 'tipo' => 'Reembolso', 'numero' => 'RR-001', 'fecha' => Carbon::today(), 'solicitante_id' => $treasury->id, 'concepto' => 'Movilidad', 'monto' => 10, 'estado' => 'Autorizado']);

    $this->actingAs($treasury);
    Livewire::test('admin.reimbursements')->call('attend', $reembolso->id)->assertHasErrors('evidenciaAtencion');
});
