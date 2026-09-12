<?php

namespace App\Livewire\Tramites;

use App\Models\AutorizacionCompra;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\History;
use App\Models\Notificacion;
use App\Models\Proveedor;
use App\Models\SolicitudTesoreria;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class Quotations extends Component
{
    use WithFileUploads;

    public Tramite $tramite;

    public string $proveedorNombre = '';

    public string $proveedorDocumento = '';

    public ?int $proveedorSeleccionado = null;

    public string $tipoSustento = 'Cotización';

    public string $fecha = '';

    public string $observacion = '';

    public array $items = [];

    public array $archivos = [];

    public array $seleccion = [];

    public string $motivoAnulacion = '';

    public function mount(Tramite $tramite): void
    {
        abort_unless($tramite->tipo === 'REQ', 404);
        abort_unless(auth()->user()->hasAnyRole(['Logística', 'Administración', 'Sistemas']), 403);
        abort_unless(auth()->user()->obra_activa_id === $tramite->obra_id, 404);
        $this->tramite = $tramite->load(['items', 'cotizaciones.proveedor', 'cotizaciones.items.item', 'autorizacionesCompra.items.proveedor']);
        $this->fecha = now()->format('Y-m-d');
        $this->items = $tramite->items->mapWithKeys(fn ($item) => [$item->id => ['cantidad' => (float) $item->comprar, 'precio_unitario' => 0]])->all();
    }

    public function crearProveedor(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Logística', 'Administración', 'Sistemas']), 403);
        $this->validate(['proveedorNombre' => 'required|string|max:255', 'proveedorDocumento' => 'nullable|string|max:30']);

        $exists = Proveedor::whereRaw('lower(nombre) = ?', [mb_strtolower(trim($this->proveedorNombre))])->exists();
        if ($exists) {
            $this->addError('proveedorNombre', 'Ya existe un proveedor con ese nombre.');

            return;
        }

        Proveedor::create(['nombre' => trim($this->proveedorNombre), 'documento' => trim($this->proveedorDocumento) ?: null]);
        $this->reset(['proveedorNombre', 'proveedorDocumento']);
        session()->flash('status', 'Proveedor registrado correctamente.');
    }

    public function crearCotizacion(?int $proveedorId): void
    {
        abort_unless(auth()->user()->hasRole('Logística'), 403);
        $this->validate(['proveedorSeleccionado' => 'required|exists:proveedores,id']);
        $this->validate([
            'tipoSustento' => 'required|in:Cotización,Proveedor habitual',
            'fecha' => 'required|date',
            'observacion' => $this->tipoSustento === 'Proveedor habitual' ? 'required|string' : 'nullable|string',
            'archivos' => 'array|max:10',
            'archivos.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $proveedor = Proveedor::where('active', true)->findOrFail($proveedorId);
        $itemsValidos = collect($this->items)->filter(fn ($item) => (float) ($item['cantidad'] ?? 0) > 0 && (float) ($item['precio_unitario'] ?? 0) > 0);
        abort_if($itemsValidos->isEmpty(), 422, 'La cotización debe incluir al menos un ítem con cantidad y precio.');
        if ($this->tipoSustento === 'Cotización' && count($this->archivos) === 0) {
            $this->addError('archivos', 'Adjunta el sustento de la cotización.');

            return;
        }

        DB::transaction(function () use ($proveedor, $itemsValidos): void {
            $cotizacion = Cotizacion::create([
                'tramite_id' => $this->tramite->id,
                'proveedor_id' => $proveedor->id,
                'creado_por' => auth()->id(),
                'tipo_sustento' => $this->tipoSustento,
                'fecha' => $this->fecha,
                'observacion' => $this->observacion ?: null,
            ]);

            foreach ($itemsValidos as $itemId => $item) {
                CotizacionItem::create(['cotizacion_id' => $cotizacion->id, 'item_id' => $itemId, 'cantidad' => $item['cantidad'], 'precio_unitario' => $item['precio_unitario']]);
            }
            foreach ($this->archivos as $archivo) {
                $cotizacion->archivos()->create(['nombre_original' => $archivo->getClientOriginalName(), 'nombre_archivo' => $archivo->store('cotizaciones', 'public')]);
            }
            $this->tramite->update(['estado' => 'Cotizaciones en gestión']);
            History::create(['tramite_id' => $this->tramite->id, 'usuario_id' => auth()->id(), 'accion' => "Cotización creada para {$proveedor->nombre}"]);
        });

        $this->archivos = [];
        $this->tramite->refresh()->load(['items', 'cotizaciones.proveedor', 'cotizaciones.items.item', 'autorizacionesCompra.items.proveedor']);
        session()->flash('status', 'Cotización guardada como borrador.');
    }

    public function enviar(int $cotizacionId): void
    {
        abort_unless(auth()->user()->hasRole('Logística'), 403);
        $cotizacion = $this->tramite->cotizaciones()->findOrFail($cotizacionId);
        abort_unless($cotizacion->estado === 'Borrador', 400);
        $cotizacion->update(['estado' => 'Enviada a Administración', 'enviado_fecha' => now()]);
        $this->tramite->update(['estado' => 'Pendiente de Administración']);
        History::create(['tramite_id' => $this->tramite->id, 'usuario_id' => auth()->id(), 'accion' => 'Cotización enviada a Administración']);
        foreach (User::role('Administración')->get() as $user) {
            Notificacion::create(['usuario_id' => $user->id, 'tramite_id' => $this->tramite->id, 'titulo' => 'Cotización pendiente de autorización', 'mensaje' => $this->tramite->tracking, 'tipo' => 'accion']);
        }
        $this->tramite->refresh()->load(['items', 'cotizaciones.proveedor', 'cotizaciones.items.item', 'autorizacionesCompra.items.proveedor']);
    }

    public function autorizar(): void
    {
        abort_unless(auth()->user()->hasRole('Administración'), 403);
        $cotizaciones = $this->tramite->cotizaciones()->whereIn('estado', ['Enviada a Administración', 'Autorizada'])->with('items')->get();
        $seleccionados = collect($this->seleccion)->filter(fn ($value) => (float) $value > 0);
        abort_if($seleccionados->isEmpty(), 422, 'Selecciona cantidades para autorizar.');

        DB::transaction(function () use ($cotizaciones, $seleccionados): void {
            $autorizacion = AutorizacionCompra::create(['tramite_id' => $this->tramite->id, 'autorizado_por' => auth()->id(), 'estado' => 'Autorizada', 'fecha' => now()]);
            $totales = [];
            foreach ($cotizaciones as $cotizacion) {
                foreach ($cotizacion->items as $item) {
                    $cantidad = (float) ($seleccionados[$item->id] ?? 0);
                    if ($cantidad <= 0) {
                        continue;
                    }
                    abort_if($cantidad > (float) $item->cantidad, 422, 'La cantidad autorizada supera la cotizada.');
                    $autorizada = (float) $item->item->autorizaciones()
                        ->whereHas('autorizacion', fn ($query) => $query->where('estado', 'Autorizada'))
                        ->sum('cantidad');
                    abort_if(
                        $autorizada + $cantidad > (float) $item->item->comprar + 0.01,
                        422,
                        'La cantidad autorizada supera lo pendiente de compra del ítem.'
                    );
                    $subtotal = round($cantidad * (float) $item->precio_unitario, 2);
                    $autorizacion->items()->create(['cotizacion_id' => $cotizacion->id, 'item_id' => $item->item_id, 'proveedor_id' => $cotizacion->proveedor_id, 'cantidad' => $cantidad, 'precio_unitario' => $item->precio_unitario, 'subtotal' => $subtotal]);
                    $totales[$cotizacion->proveedor_id] = ($totales[$cotizacion->proveedor_id] ?? 0) + $subtotal;
                }
            }
            foreach ($totales as $proveedorId => $monto) {
                $proveedor = Proveedor::find($proveedorId);
                SolicitudTesoreria::create([
                    'tramite_id' => $this->tramite->id,
                    'autorizacion_id' => $autorizacion->id,
                    'solicitado_por' => auth()->id(),
                    'motivo' => 'Pago de cotización autorizada del proveedor '.($proveedor->nombre ?? $proveedorId),
                    'monto' => $monto,
                    'estado' => 'Pendiente',
                    'origen' => 'REQ-Cotizacion',
                    'fecha_solicitud' => now(),
                ]);
            }
            $this->tramite->update(['estado' => 'En gestión de compra']);
            History::create(['tramite_id' => $this->tramite->id, 'usuario_id' => auth()->id(), 'accion' => 'Cotización autorizada por Administración']);

            foreach (User::role('Tesorería')->activeAssignedToObra($this->tramite->obra_id)->get() as $tesoreria) {
                Notificacion::create([
                    'usuario_id' => $tesoreria->id,
                    'tramite_id' => $this->tramite->id,
                    'titulo' => 'Compra autorizada pendiente de pago',
                    'mensaje' => "La compra del requerimiento {$this->tramite->tracking} fue autorizada y tiene pagos pendientes.",
                    'tipo' => 'accion',
                ]);
            }
        });
        $this->tramite->refresh()->load(['items', 'cotizaciones.proveedor', 'cotizaciones.items.item', 'autorizacionesCompra.items.proveedor']);
        session()->flash('status', 'Compra autorizada y enviada a Tesorería.');
    }

    public function anularAutorizacion(int $autorizacionId): void
    {
        abort_unless(auth()->user()->hasRole('Administración'), 403);
        $autorizacion = $this->tramite->autorizacionesCompra()->findOrFail($autorizacionId);
        abort_unless($autorizacion->estado === 'Autorizada', 400);
        $this->validate(['motivoAnulacion' => 'required|string|min:5|max:1000']);

        abort_if(
            $autorizacion->solicitudesTesoreria()->where('estado', 'Atendida')->exists(),
            422,
            'No se puede anular una autorización con pagos atendidos.'
        );

        DB::transaction(function () use ($autorizacion): void {
            $autorizacion->update(['estado' => 'Anulada', 'motivo_anulacion' => $this->motivoAnulacion, 'anulada_fecha' => now()]);
            $autorizacion->solicitudesTesoreria()->where('estado', 'Pendiente')->update(['estado' => 'Anulado']);
            $this->tramite->update(['estado' => 'Cotizaciones en gestión']);
            History::create(['tramite_id' => $this->tramite->id, 'usuario_id' => auth()->id(), 'accion' => 'Autorización de compra anulada: '.$this->motivoAnulacion]);
        });

        $this->motivoAnulacion = '';
        $this->tramite->refresh()->load(['items', 'cotizaciones.proveedor', 'cotizaciones.items.item', 'autorizacionesCompra.items.proveedor']);
        session()->flash('status', 'Autorización anulada correctamente.');
    }

    public function getAvanceProperty()
    {
        return $this->tramite->items->where('comprar', '>', 0)->map(function ($item) {
            $autorizado = (float) $item->autorizaciones()
                ->whereHas('autorizacion', fn ($q) => $q->where('estado', 'Autorizada'))
                ->sum('cantidad');

            return [
                'descripcion' => $item->descripcion,
                'requerido' => (float) $item->comprar,
                'autorizado' => $autorizado,
                'pendiente' => max((float) $item->comprar - $autorizado, 0),
            ];
        })->values();
    }

    public function render()
    {
        return view('livewire.tramites.quotations', ['proveedores' => Proveedor::where('active', true)->orderBy('nombre')->get()]);
    }
}
