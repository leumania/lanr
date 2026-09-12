<?php

use App\Livewire\Tramites\Quotations;
use App\Models\Attachment;
use App\Models\CompraLogistica;
use App\Models\Item;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\AutorizacionCompra;
use App\Models\Regularizacion;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function coverageRole(string $name): void
{
    Role::findOrCreate($name, 'web');
}

function coverageTramite(Obra $obra, User $user, string $tipo = 'REQ'): Tramite
{
    return Tramite::create([
        'obra_id' => $obra->id,
        'tracking' => uniqid($tipo . '-COVER-'),
        'tipo' => $tipo,
        'numero' => '001',
        'fecha' => Carbon::today(),
        'proyecto' => 'Proyecto',
        'lugar' => 'Lugar',
        'abono' => $tipo === 'SP' ? 100 : null,
        'estado' => 'En gestión de compra',
        'creador_id' => $user->id,
    ]);
}

test('attachment download is limited to the active work', function () {
    Storage::fake('public');
    $obra = Obra::create(['nombre' => 'Archivo actual', 'codigo' => 'FILE-A', 'activa' => true]);
    $otraObra = Obra::create(['nombre' => 'Archivo ajeno', 'codigo' => 'FILE-B', 'activa' => true]);
    $user = User::factory()->create(['obra_activa_id' => $obra->id]);
    $owner = User::factory()->create(['obra_activa_id' => $otraObra->id]);
    $tramite = coverageTramite($otraObra, $owner);
    $attachment = Attachment::create(['tramite_id' => $tramite->id, 'nombre_original' => 'evidencia.pdf', 'nombre_archivo' => 'adjuntos/evidencia.pdf', 'orden' => 1]);
    Storage::disk('public')->put($attachment->nombre_archivo, 'contenido');

    $this->actingAs($user)->get(route('tramites.attachments.download', [$tramite, $attachment]))->assertNotFound();
});

test('authorization rejects quantities above the pending purchase balance', function () {
    coverageRole('Administración');
    $obra = Obra::create(['nombre' => 'Cotización límite', 'codigo' => 'QUO-LIMIT', 'activa' => true]);
    $admin = User::factory()->create(['obra_activa_id' => $obra->id]);
    $admin->assignRole('Administración');
    $tramite = coverageTramite($obra, $admin);
    $item = Item::create(['tramite_id' => $tramite->id, 'seccion' => 'Ejecución de Obra', 'nro' => 1, 'descripcion' => 'Cemento', 'unidad' => 'UND', 'cantidad' => 5, 'comprar' => 5]);
    $proveedor = Proveedor::create(['nombre' => 'Proveedor límite']);
    $previousQuotation = Cotizacion::create(['tramite_id' => $tramite->id, 'proveedor_id' => $proveedor->id, 'creado_por' => $admin->id, 'tipo_sustento' => 'Cotización', 'fecha' => Carbon::today(), 'estado' => 'Autorizada']);
    $previousItem = $previousQuotation->items()->create(['item_id' => $item->id, 'cantidad' => 5, 'precio_unitario' => 10]);
    $previousAuthorization = AutorizacionCompra::create(['tramite_id' => $tramite->id, 'autorizado_por' => $admin->id, 'estado' => 'Autorizada', 'fecha' => now()]);
    $previousAuthorization->items()->create(['cotizacion_id' => $previousQuotation->id, 'item_id' => $item->id, 'proveedor_id' => $proveedor->id, 'cantidad' => 5, 'precio_unitario' => 10, 'subtotal' => 50]);
    $newQuotation = Cotizacion::create(['tramite_id' => $tramite->id, 'proveedor_id' => $proveedor->id, 'creado_por' => $admin->id, 'tipo_sustento' => 'Cotización', 'fecha' => Carbon::today(), 'estado' => 'Enviada a Administración']);
    $newItem = $newQuotation->items()->create(['item_id' => $item->id, 'cantidad' => 1, 'precio_unitario' => 12]);

    $this->actingAs($admin);
    Livewire::test(Quotations::class, ['tramite' => $tramite])
        ->set("seleccion.{$newItem->id}", 1)
        ->call('autorizar')
        ->assertStatus(422);
});

test('regularization rejects an amount that does not reconcile with purchases', function () {
    coverageRole('Logística');
    $obra = Obra::create(['nombre' => 'Regularización', 'codigo' => 'REG-COVER', 'activa' => true]);
    $logistics = User::factory()->create(['obra_activa_id' => $obra->id]);
    $logistics->assignRole('Logística');
    $tramite = coverageTramite($obra, $logistics);
    CompraLogistica::create(['tramite_id' => $tramite->id, 'fecha_compra' => Carbon::today(), 'tipo_comprobante' => 'Factura', 'monto' => 100, 'creado_por' => $logistics->id]);
    $regularizacion = Regularizacion::create(['tramite_id' => $tramite->id, 'responsable_id' => $logistics->id, 'tipo' => 'Compra', 'descripcion' => 'Detalle', 'estado' => 'Pendiente']);
    $regularizacion->detalles()->create(['descripcion' => 'Detalle distinto', 'cantidad' => 1, 'precio_unitario' => 50, 'precio_total' => 50]);

    $this->actingAs($logistics);
    Livewire::test('tramites.request-detail', ['tramite' => $tramite])
        ->set('archivo_regularizacion', UploadedFile::fake()->create('regularizacion.pdf', 10, 'application/pdf'))
        ->call('regularizar', $regularizacion->id)
        ->assertHasErrors('archivo_regularizacion');

    expect($regularizacion->fresh()->estado)->toBe('Pendiente');
});

test('request search API respects active work and creator privacy', function () {
    $obra = Obra::create(['nombre' => 'Búsqueda actual', 'codigo' => 'SEARCH-A', 'activa' => true]);
    $otherWork = Obra::create(['nombre' => 'Búsqueda ajena', 'codigo' => 'SEARCH-B', 'activa' => true]);
    $user = User::factory()->create(['obra_activa_id' => $obra->id]);
    $owner = User::factory()->create(['obra_activa_id' => $otherWork->id]);
    $visible = coverageTramite($obra, $user);
    $visible->update(['tracking' => 'REQ-SEARCH-VISIBLE']);
    $hidden = coverageTramite($obra, $owner);
    $hidden->update(['tracking' => 'REQ-SEARCH-HIDDEN', 'estado' => 'Pendiente de mi revisión']);
    $foreign = coverageTramite($otherWork, $owner);
    $foreign->update(['tracking' => 'REQ-SEARCH-FOREIGN']);

    $response = $this->actingAs($user)->getJson(route('tramites.search.api', ['q' => 'REQ-SEARCH']));

    $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.tracking', 'REQ-SEARCH-VISIBLE');
});

test('creator can confirm review and delete an unsubmitted request', function () {
    $obra = Obra::create(['nombre' => 'Revisión creador', 'codigo' => 'CREATOR-A', 'activa' => true]);
    $user = User::factory()->create(['obra_activa_id' => $obra->id]);
    $tramite = coverageTramite($obra, $user);
    $tramite->update(['estado' => 'Pendiente de mi revisión']);

    $this->actingAs($user);
    Livewire::test('tramites.request-detail', ['tramite' => $tramite])->call('creatorApprove');
    expect($tramite->fresh()->estado)->toBe('Pendiente de aprobación');

    $deletable = coverageTramite($obra, $user);
    $deletable->update(['estado' => 'Pendiente de mi revisión']);
    Livewire::test('tramites.request-detail', ['tramite' => $deletable])->call('deleteRequest');
    expect(Tramite::find($deletable->id))->toBeNull();
});