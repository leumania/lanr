<?php

namespace App\Livewire\Admin;

use App\Models\MonedaCatalogo;
use App\Models\Orden;
use App\Models\UnidadCatalogo;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class Orders extends Component
{
    use WithFileUploads;

    public string $tipoOrden = 'Orden de compra';

    public string $numero = '';

    public string $fecha = '';

    public string $proveedor = '';

    public string $documentoProveedor = '';

    public string $descripcion = '';

    public string $total = '';

    public string $moneda = 'PEN';

    public array $items = [];

    public array $archivos = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->obra_activa_id, 403);
        $this->fecha = now()->format('Y-m-d');
        $this->numero = $this->siguienteNumero();
        $this->addItem();
    }

    public function updatedTipoOrden(): void
    {
        $this->numero = $this->siguienteNumero();
    }

    protected function siguienteNumero(): string
    {
        $ultimo = Orden::where('obra_id', auth()->user()->obra_activa_id)
            ->where('tipo_orden', $this->tipoOrden)
            ->pluck('numero')
            ->map(fn ($numero) => (int) preg_replace('/\D/', '', (string) $numero))
            ->max();

        return (string) (($ultimo ?? 0) + 1);
    }

    public function getUnidadesProperty()
    {
        return UnidadCatalogo::where('active', true)
            ->orderBy('abreviatura')
            ->pluck('abreviatura')
            ->unique()
            ->values();
    }

    public function getMonedasProperty()
    {
        return MonedaCatalogo::where('active', true)->orderBy('codigo')->get();
    }

    public function addItem(): void
    {
        $this->items[] = ['descripcion' => '', 'unidad' => '', 'cantidad' => 1, 'precio_unitario' => 0];
    }

    public function removeItem(int $index): void
    {
        abort_if(count($this->items) <= 1, 422, 'Debe conservar al menos un ítem.');
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Gerencia de Obra', 'Administración', 'Sistemas']), 403);
        $this->validate([
            'tipoOrden' => 'required|in:Orden de compra,Orden de servicio',
            'numero' => 'required|string|max:50', 'fecha' => 'required|date',
            'proveedor' => 'required|string|max:255', 'descripcion' => 'required|string',
            'total' => 'required|numeric|min:0.01', 'moneda' => 'required|string|max:10',
            'items' => 'required|array|min:1', 'items.*.descripcion' => 'required|string',
            'items.*.unidad' => 'nullable|string|max:50', 'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'archivos' => 'array|max:5', 'archivos.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $detalleTotal = collect($this->items)->sum(fn (array $item): float => (float) $item['cantidad'] * (float) $item['precio_unitario']);

        if (abs($detalleTotal - (float) $this->total) > 0.01) {
            $this->addError('total', 'El total debe coincidir con el detalle de la orden.');

            return;
        }

        $duplicado = Orden::where('obra_id', auth()->user()->obra_activa_id)
            ->where('tipo_orden', $this->tipoOrden)
            ->where('numero', $this->numero)
            ->exists();

        if ($duplicado) {
            $this->addError('numero', "El número {$this->numero} ya fue registrado por otra orden. Modifique el número e inténtelo nuevamente.");

            return;
        }

        DB::transaction(function (): void {
            $orden = Orden::create(['obra_id' => auth()->user()->obra_activa_id, 'tipo_orden' => $this->tipoOrden, 'numero' => $this->numero, 'fecha' => $this->fecha, 'proveedor' => $this->proveedor, 'documento_proveedor' => $this->documentoProveedor ?: null, 'moneda' => $this->moneda, 'descripcion' => $this->descripcion, 'total' => $this->total, 'estado' => 'Pendiente de aprobación', 'creador_id' => auth()->id()]);
            foreach ($this->items as $item) {
                $orden->items()->create(['descripcion' => $item['descripcion'], 'unidad' => $item['unidad'] ?: null, 'cantidad' => $item['cantidad'], 'precio_unitario' => $item['precio_unitario'], 'monto' => round((float) $item['cantidad'] * (float) $item['precio_unitario'], 2)]);
            }
            foreach ($this->archivos as $archivo) {
                $orden->adjuntos()->create(['nombre_original' => $archivo->getClientOriginalName(), 'nombre_archivo' => $archivo->store('ordenes', 'public')]);
            }
        });

        $this->reset(['numero', 'proveedor', 'documentoProveedor', 'descripcion', 'total', 'moneda', 'items', 'archivos']);
        $this->fecha = now()->format('Y-m-d');
        $this->moneda = 'PEN';
        $this->numero = $this->siguienteNumero();
        $this->addItem();
        session()->flash('status', 'Orden registrada correctamente.');
    }

    public function approve(int $id): void
    {
        $order = Orden::where('obra_id', auth()->user()->obra_activa_id)->findOrFail($id);
        $user = auth()->user();
        abort_unless($order->estado === 'Pendiente de aprobación', 400);
        $field = match (true) {
            $user->hasRole('Gerencia de Obra') => 'vobo_gerencia_obra', $user->hasRole('Administración') => 'vobo_administracion', $user->hasRole('Gerencia General') => 'vobo_gerencia_general', default => null
        };
        abort_unless($field, 403);
        abort_unless(! ($field === 'vobo_administracion' && ! $order->vobo_gerencia_obra), 400);
        abort_unless(! ($field === 'vobo_gerencia_general' && (! $order->vobo_gerencia_obra || ! $order->vobo_administracion)), 400);
        $order->update([$field => $user->id]);
        $order->refresh();
        $order->update(['estado' => $order->vobo_gerencia_obra && $order->vobo_administracion && $order->vobo_gerencia_general ? 'Aprobada' : 'Pendiente de aprobación']);
    }

    public function render()
    {
        return view('livewire.admin.orders', ['ordenes' => Orden::where('obra_id', auth()->user()->obra_activa_id)->with(['creador', 'adjuntos'])->latest()->get()]);
    }
}
