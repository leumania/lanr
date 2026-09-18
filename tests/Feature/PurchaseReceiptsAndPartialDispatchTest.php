<?php

use App\Models\AutorizacionCompra;
use App\Models\CompraLogistica;
use App\Models\Cotizacion;
use App\Models\Despacho;
use App\Models\GestionLogistica;
use App\Models\Item;
use App\Models\Obra;
use App\Models\Proveedor;
use App\Models\SolicitudTesoreria;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function purchaseReceiptsRole(string $name): void
{
    Role::findOrCreate($name, 'web');
}

test('comprobante de compra se vincula al proveedor/solicitud y no puede superar lo pagado ni lo autorizado', function () {
    Storage::fake('public');
    purchaseReceiptsRole('Logística');

    $obra = Obra::create(['nombre' => 'Recibos', 'codigo' => 'RECIBOS', 'activa' => true]);
    $logistica = User::factory()->create(['obra_activa_id' => $obra->id]);
    $logistica->assignRole('Logística');

    $tramite = Tramite::create([
        'obra_id' => $obra->id, 'tracking' => 'REQ-RECIBOS-1', 'tipo' => 'REQ', 'numero' => '1-2026',
        'fecha' => Carbon::today(), 'proyecto' => 'P', 'lugar' => 'L', 'estado' => 'En gestión de compra',
        'creador_id' => $logistica->id,
    ]);
    $item = Item::create(['tramite_id' => $tramite->id, 'seccion' => 'Ejecución de Obra', 'nro' => 1, 'descripcion' => 'Cemento', 'unidad' => 'BOL', 'cantidad' => 10, 'comprar' => 10]);

    $proveedorA = Proveedor::create(['nombre' => 'Proveedor A']);

    $cotizacion = Cotizacion::create(['tramite_id' => $tramite->id, 'proveedor_id' => $proveedorA->id, 'creado_por' => $logistica->id, 'tipo_sustento' => 'Cotización', 'fecha' => Carbon::today(), 'estado' => 'Autorizada']);
    $autorizacion = AutorizacionCompra::create(['tramite_id' => $tramite->id, 'autorizado_por' => $logistica->id, 'estado' => 'Autorizada', 'fecha' => now()]);
    $autorizacion->items()->create(['cotizacion_id' => $cotizacion->id, 'item_id' => $item->id, 'proveedor_id' => $proveedorA->id, 'cantidad' => 10, 'precio_unitario' => 20, 'subtotal' => 200]);

    $solicitudA = SolicitudTesoreria::create([
        'tramite_id' => $tramite->id, 'autorizacion_id' => $autorizacion->id, 'proveedor_id' => $proveedorA->id,
        'solicitado_por' => $logistica->id, 'motivo' => 'Pago proveedor A', 'monto' => 200,
        'estado' => 'Atendida', 'origen' => 'REQ-Cotizacion', 'fecha_solicitud' => now(), 'fecha_atencion' => now(),
    ]);

    GestionLogistica::create(['tramite_id' => $tramite->id, 'estado_pago' => 'Pagado por Tesorería', 'forma_pago' => 'Tesorería']);

    $this->actingAs($logistica);

    $component = Livewire::test('tramites.request-detail', ['tramite' => $tramite]);
    $component->call('addComprobanteDefinitivo');

    // Monto que excede el saldo pagado al proveedor A (200) debe rechazarse.
    $component->set('comprobantes_definitivos.0.solicitud_tesoreria_id', $solicitudA->id)
        ->set('comprobantes_definitivos.0.fecha_compra', now()->format('Y-m-d'))
        ->set('comprobantes_definitivos.0.nro_comprobante', 'F001-1')
        ->set('comprobantes_definitivos.0.monto', 250)
        ->set("comprobantes_definitivos.0.items.{$item->id}", 10)
        ->set('comprobantes_definitivos.0.archivo', UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'))
        ->call('registrarComprobantesDefinitivos')
        ->assertHasErrors('comprobantes_definitivos.0.monto');

    expect(CompraLogistica::count())->toBe(0);

    // Cantidad que excede lo autorizado (10) al proveedor A también debe rechazarse.
    $component->set('comprobantes_definitivos.0.monto', 200)
        ->set("comprobantes_definitivos.0.items.{$item->id}", 15)
        ->call('registrarComprobantesDefinitivos')
        ->assertHasErrors("comprobantes_definitivos.0.items.{$item->id}");

    expect(CompraLogistica::count())->toBe(0);

    // Dentro de lo pagado y autorizado, debe registrarse correctamente y vincular proveedor+solicitud.
    $component->set("comprobantes_definitivos.0.items.{$item->id}", 10)
        ->call('registrarComprobantesDefinitivos')
        ->assertHasNoErrors();

    $compra = CompraLogistica::firstOrFail();
    expect($compra->proveedor_id)->toBe($proveedorA->id)
        ->and($compra->solicitud_tesoreria_id)->toBe($solicitudA->id)
        ->and((float) $compra->detalles()->sum('cantidad_comprada'))->toBe(10.0);
});

test('el despacho a obra admite envios parciales por item y solo cierra el tramite cuando todo fue despachado', function () {
    Storage::fake('public');
    purchaseReceiptsRole('Logística');
    purchaseReceiptsRole('Gerencia de Obra');
    purchaseReceiptsRole('Control y Planeamiento');

    $obra = Obra::create(['nombre' => 'Despacho parcial', 'codigo' => 'DESP-PARC', 'activa' => true]);
    $logistica = User::factory()->create(['obra_activa_id' => $obra->id]);
    $logistica->assignRole('Logística');

    $tramite = Tramite::create([
        'obra_id' => $obra->id, 'tracking' => 'REQ-DESPP-1', 'tipo' => 'REQ', 'numero' => '1-2026',
        'fecha' => Carbon::today(), 'proyecto' => 'P', 'lugar' => 'L', 'estado' => 'En gestión de compra',
        'creador_id' => $logistica->id,
    ]);
    $itemA = Item::create(['tramite_id' => $tramite->id, 'seccion' => 'Ejecución de Obra', 'nro' => 1, 'descripcion' => 'Cemento', 'unidad' => 'BOL', 'cantidad' => 10, 'comprar' => 10]);
    $itemB = Item::create(['tramite_id' => $tramite->id, 'seccion' => 'Ejecución de Obra', 'nro' => 2, 'descripcion' => 'Fierro', 'unidad' => 'UND', 'cantidad' => 4, 'comprar' => 4]);

    $compra = CompraLogistica::create(['tramite_id' => $tramite->id, 'fecha_compra' => Carbon::today(), 'tipo_comprobante' => 'Factura', 'monto' => 500, 'creado_por' => $logistica->id]);
    $compra->detalles()->create(['item_id' => $itemA->id, 'cantidad_comprada' => 10]);
    $compra->detalles()->create(['item_id' => $itemB->id, 'cantidad_comprada' => 4]);

    GestionLogistica::create(['tramite_id' => $tramite->id, 'estado_pago' => 'Pagado por Logística']);

    $this->actingAs($logistica);

    // Primer despacho: solo una parte del ítem A. El tramite debe seguir "En gestión de compra".
    Livewire::test('tramites.request-detail', ['tramite' => $tramite])
        ->set('envio.guia_opcion', 'pendiente')
        ->set("detalle_despacho.{$itemA->id}", 6)
        ->set('evidencias_envio', [UploadedFile::fake()->image('evidencia1.jpg')])
        ->call('enviarAObra')
        ->assertHasNoErrors();

    $tramite->refresh();
    expect($tramite->estado)->toBe('En gestión de compra')
        ->and(Despacho::where('tramite_id', $tramite->id)->count())->toBe(1);

    // Intentar despachar más de lo pendiente del ítem A (quedan 4) debe rechazarse.
    Livewire::test('tramites.request-detail', ['tramite' => $tramite->fresh()])
        ->set('envio.guia_opcion', 'pendiente')
        ->set("detalle_despacho.{$itemA->id}", 5)
        ->set('evidencias_envio', [UploadedFile::fake()->image('evidencia2.jpg')])
        ->call('enviarAObra')
        ->assertHasErrors("detalle_despacho.{$itemA->id}");

    // Completar el resto del ítem A y todo el ítem B: ahora sí debe cerrar el envío.
    Livewire::test('tramites.request-detail', ['tramite' => $tramite->fresh()])
        ->set('envio.guia_opcion', 'pendiente')
        ->set("detalle_despacho.{$itemA->id}", 4)
        ->set("detalle_despacho.{$itemB->id}", 4)
        ->set('evidencias_envio', [UploadedFile::fake()->image('evidencia3.jpg')])
        ->call('enviarAObra')
        ->assertHasNoErrors();

    $tramite->refresh();
    expect($tramite->estado)->toBe('Enviado a obra')
        ->and(Despacho::where('tramite_id', $tramite->id)->count())->toBe(2);
});
