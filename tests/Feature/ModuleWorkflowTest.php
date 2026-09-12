<?php

use App\Livewire\Admin\Orders;
use App\Livewire\Admin\Reimbursements;
use App\Livewire\Tramites\Quotations;
use App\Livewire\Tramites\SpAdvanced;
use App\Models\AutorizacionCompra;
use App\Models\GestionSp;
use App\Models\Obra;
use App\Models\Orden;
use App\Models\Reembolso;
use App\Models\SpPagoMultiple;
use App\Models\Cotizacion;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function moduleRole(string $name): void
{
    Role::findOrCreate($name, 'web');
}

function moduleObra(string $codigo): Obra
{
    return Obra::create(['nombre' => $codigo, 'codigo' => $codigo, 'activa' => true]);
}

test('orders require sequential approvals', function () {
    foreach (['Gerencia de Obra', 'Administración', 'Gerencia General'] as $role) {
        moduleRole($role);
    }

    $obra = moduleObra('ORD-TEST');
    $creator = User::factory()->create(['obra_activa_id' => $obra->id]);
    $creator->assignRole('Gerencia de Obra');
    $obraManager = User::factory()->create(['obra_activa_id' => $obra->id]);
    $admin = User::factory()->create(['obra_activa_id' => $obra->id]);
    $generalManager = User::factory()->create(['obra_activa_id' => $obra->id]);
    $obraManager->assignRole('Gerencia de Obra');
    $admin->assignRole('Administración');
    $generalManager->assignRole('Gerencia General');

    $this->actingAs($creator);
    Livewire::test(Orders::class)
        ->set('numero', 'ORD-001')
        ->set('proveedor', 'Proveedor de prueba')
        ->set('descripcion', 'Servicio de prueba')
        ->set('items.0.descripcion', 'Servicio de prueba')
        ->set('items.0.unidad', 'UND')
        ->set('items.0.cantidad', 1)
        ->set('items.0.precio_unitario', 100)
        ->set('total', '100.00')
        ->call('save');

    $order = Orden::firstOrFail();

    $this->actingAs($admin);
    Livewire::test(Orders::class)->call('approve', $order->id)->assertStatus(400);

    $this->actingAs($obraManager);
    Livewire::test(Orders::class)->call('approve', $order->id);
    $this->actingAs($admin);
    Livewire::test(Orders::class)->call('approve', $order->id);
    $this->actingAs($generalManager);
    Livewire::test(Orders::class)->call('approve', $order->id);

    expect(Orden::find($order->id)->estado)->toBe('Aprobada');
});

test('reimbursements move from pending to authorized to attended', function () {
    foreach (['Gerencia de Obra', 'Administración', 'Tesorería'] as $role) {
        moduleRole($role);
    }

    $obra = moduleObra('RMB-TEST');
    $requester = User::factory()->create(['obra_activa_id' => $obra->id]);
    $requester->assignRole('Gerencia de Obra');
    $admin = User::factory()->create(['obra_activa_id' => $obra->id]);
    $treasury = User::factory()->create(['obra_activa_id' => $obra->id]);
    $admin->assignRole('Administración');
    $treasury->assignRole('Tesorería');

    $this->actingAs($requester);
    Livewire::test(Reimbursements::class)
        ->set('numero', 'RMB-001')
        ->set('concepto', 'Movilidad')
        ->set('monto', '75.50')
        ->call('save');

    $reembolso = Reembolso::firstOrFail();
    expect($reembolso->estado)->toBe('Pendiente');

    $this->actingAs($admin);
    Livewire::test(Reimbursements::class)->call('authorizeReimbursement', $reembolso->id);
    expect($reembolso->fresh()->estado)->toBe('Autorizado');

    $this->actingAs($treasury);
    Livewire::test(Reimbursements::class)
        ->set('evidenciaAtencion', UploadedFile::fake()->create('pago.pdf', 10, 'application/pdf'))
        ->call('attend', $reembolso->id);
    expect($reembolso->fresh()->estado)->toBe('Atendido');
});

test('multiple SP payments preserve their accumulated total', function () {
    $obra = moduleObra('SP-TEST');
    $user = User::factory()->create(['obra_activa_id' => $obra->id]);
    $tramite = Tramite::create([
        'obra_id' => $obra->id,
        'tracking' => 'SP-TEST-001',
        'tipo' => 'SP',
        'numero' => '001',
        'fecha' => Carbon::today(),
        'proyecto' => 'Proyecto',
        'lugar' => 'Lugar',
        'abono' => 100,
        'estado' => 'Pago parcial',
        'creador_id' => $user->id,
    ]);
    $gestion = GestionSp::create(['tramite_id' => $tramite->id, 'asignado_pago' => 'Tesorería']);
    $gestion->pagosMultiples()->createMany([
        ['pagado_por' => $user->id, 'medio_pago' => 'Transferencia', 'monto' => 40, 'fecha_pago' => Carbon::today()],
        ['pagado_por' => $user->id, 'medio_pago' => 'Transferencia', 'monto' => 60, 'fecha_pago' => Carbon::today()],
    ]);

    expect((float) $gestion->pagosMultiples()->sum('monto'))->toBe(100.0);
});

test('administration can cancel an unpaid purchase authorization', function () {
    moduleRole('Administración');
    $obra = moduleObra('CAN-TEST');
    $admin = User::factory()->create(['obra_activa_id' => $obra->id]);
    $admin->assignRole('Administración');
    $tramite = Tramite::create(['obra_id' => $obra->id, 'tracking' => 'REQ-CAN-001', 'tipo' => 'REQ', 'numero' => '001', 'fecha' => Carbon::today(), 'proyecto' => 'Proyecto', 'lugar' => 'Lugar', 'estado' => 'En gestión de compra', 'creador_id' => $admin->id]);
    $autorizacion = AutorizacionCompra::create(['tramite_id' => $tramite->id, 'autorizado_por' => $admin->id, 'estado' => 'Autorizada', 'fecha' => now()]);

    $this->actingAs($admin);
    Livewire::test(Quotations::class, ['tramite' => $tramite])
        ->set('motivoAnulacion', 'Se requiere corregir la cotización')
        ->call('anularAutorizacion', $autorizacion->id);

    expect($autorizacion->fresh()->estado)->toBe('Anulada');
});

test('authorized staff can open advanced SP management', function () {
    moduleRole('Tesorería');
    $obra = moduleObra('ADV-TEST');
    $user = User::factory()->create(['obra_activa_id' => $obra->id]);
    $user->assignRole('Tesorería');
    $tramite = Tramite::create(['obra_id' => $obra->id, 'tracking' => 'SP-ADV-001', 'tipo' => 'SP', 'numero' => '001', 'fecha' => Carbon::today(), 'proyecto' => 'Proyecto', 'lugar' => 'Lugar', 'abono' => 10, 'estado' => 'Pendiente asignación de pago', 'creador_id' => $user->id]);

    $this->actingAs($user)->get(route('tramites.sp-advanced', $tramite))->assertOk();
    Livewire::test(SpAdvanced::class, ['tramite' => $tramite])
        ->set('modalidad', 'Transferencia')
        ->set('montoModalidad', '10')
        ->call('addModalidad')
        ->assertHasNoErrors();
});