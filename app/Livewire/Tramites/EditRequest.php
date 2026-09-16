<?php

namespace App\Livewire\Tramites;

use App\Models\History;
use App\Models\Item;
use App\Models\Tramite;
use App\Models\UnidadCatalogo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditRequest extends Component
{
    use WithFileUploads;

    const MODALIDADES = ['Transferencia', 'Depósito', 'En efectivo', 'Transferencias varias', 'Otro'];

    const BANCOS = ['Banco de la Nación', 'BBVA', 'BCP', 'Caja Trujillo', 'Otro'];

    public Tramite $tramite;

    public string $numeroSecuencial = '';

    public int $anioActual = 0;

    public string $fecha = '';

    public string $subtipo = 'Planillas';

    public string $beneficiario = '';

    public string $dniRuc = '';

    public string $responsable = '';

    public string $celular = '';

    public array $modalidades = [];

    public string $modalidadOtroTexto = '';

    public array $cuentas = [];

    public string $observaciones = '';

    public array $items = [];

    public array $imagenesExistentes = [];

    public array $imagenes = [];

    public array $adjuntos = [];

    public function mount(Tramite $tramite): void
    {
        abort_unless($tramite->obra_id === auth()->user()->obra_activa_id, 404);
        abort_unless($tramite->creador_id === auth()->id(), 403);
        abort_unless($tramite->estado === 'Pendiente de mi revisión', 400);

        $this->tramite = $tramite->load(['items.imagenes', 'attachments', 'spCuentas']);

        [$secuencia, $anio] = $this->descomponerNumero($tramite->numero, $tramite->tipo);
        $this->numeroSecuencial = $secuencia;
        $this->anioActual = $anio;

        $this->fecha = $tramite->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->subtipo = $tramite->subtipo ?: 'Planillas';
        $this->beneficiario = $tramite->beneficiario ?? '';
        $this->dniRuc = $tramite->dni_ruc ?? '';
        $this->responsable = $tramite->responsable ?? '';
        $this->celular = $tramite->celular ?? '';
        $this->observaciones = $tramite->observaciones ?? '';

        $this->modalidades = $tramite->modalidad_pago
            ? array_values(array_filter(array_map('trim', explode(',', $tramite->modalidad_pago))))
            : [];
        $otro = collect($this->modalidades)->first(fn ($m) => str_starts_with($m, 'Otro:'));
        if ($otro) {
            $this->modalidadOtroTexto = trim(substr($otro, 5));
            $this->modalidades = array_values(array_diff($this->modalidades, [$otro]));
            $this->modalidades[] = 'Otro';
        }

        $this->cuentas = $tramite->spCuentas->map(fn ($c) => [
            'banco' => in_array($c->banco, self::BANCOS, true) ? $c->banco : 'Otro',
            'bancoOtro' => in_array($c->banco, self::BANCOS, true) ? '' : $c->banco,
            'cuenta_cci' => $c->cuenta_cci ?? '',
        ])->values()->all();

        $this->items = $tramite->items->map(fn (Item $item): array => [
            'id' => $item->id,
            'seccion' => $item->seccion,
            'descripcion' => $item->descripcion,
            'concepto' => $item->descripcion,
            'unidad' => $item->unidad,
            'cantidad' => (float) $item->cantidad,
            'stock' => (float) ($item->stock ?? 0),
            'justificacion' => $item->justificacion ?? '',
            'costo' => (float) ($item->costo ?? 0),
            'prioridad' => $item->prioridad ?: 'Normal',
            'fecha_requerida' => $item->fecha_requerida?->format('Y-m-d') ?? '',
            'nro_despacho' => $item->nro_despacho ?? '',
        ])->values()->all();

        $this->imagenesExistentes = $tramite->items->values()->map(
            fn (Item $item) => $item->imagenes->map(fn ($img) => [
                'id' => $img->id,
                'url' => Storage::disk('public')->url($img->nombre_archivo),
                'nombre' => $img->nombre_original,
            ])->all()
        )->all();

        if ($this->items === []) {
            $this->addItem();
        }
    }

    protected function descomponerNumero(string $numero, string $tipo): array
    {
        $partes = explode('-', $numero, 2);

        if (count($partes) === 2) {
            return $tipo === 'SP'
                ? [$partes[1], (int) $partes[0]]
                : [$partes[0], (int) $partes[1]];
        }

        return [$numero, (int) now()->year];
    }

    public function getComprarProperty(): array
    {
        return collect($this->items)->map(
            fn ($item) => max((float) ($item['cantidad'] ?? 0) - (float) ($item['stock'] ?? 0), 0)
        )->all();
    }

    public function getMontosProperty(): array
    {
        return collect($this->items)->map(
            fn ($item) => round((float) ($item['cantidad'] ?? 0) * (float) ($item['costo'] ?? 0), 2)
        )->all();
    }

    public function getMonedaSimboloProperty(): string
    {
        return $this->tramite->moneda === 'USD' ? 'US$' : 'S/';
    }

    public function getUnidadesProperty()
    {
        return UnidadCatalogo::where('active', true)
            ->where(fn ($q) => $q->where('uso', $this->tramite->tipo)->orWhere('uso', 'AMBOS'))
            ->orderBy('abreviatura')
            ->pluck('abreviatura');
    }

    public function toggleModalidad(string $nombre): void
    {
        if (in_array($nombre, $this->modalidades, true)) {
            $this->modalidades = array_values(array_diff($this->modalidades, [$nombre]));
        } else {
            $this->modalidades[] = $nombre;
        }
    }

    public function addCuenta(): void
    {
        $this->cuentas[] = ['banco' => '', 'bancoOtro' => '', 'cuenta_cci' => ''];
    }

    public function removeCuenta(int $index): void
    {
        unset($this->cuentas[$index]);
        $this->cuentas = array_values($this->cuentas);
    }

    public function addItem(): void
    {
        $this->items[] = $this->tramite->tipo === 'REQ'
            ? ['id' => null, 'seccion' => 'Ejecución de Obra', 'descripcion' => '', 'unidad' => '', 'cantidad' => 0, 'stock' => 0, 'justificacion' => '', 'prioridad' => 'Normal', 'fecha_requerida' => '']
            : ['id' => null, 'concepto' => '', 'unidad' => '', 'cantidad' => 0, 'costo' => 0, 'nro_despacho' => ''];
        $this->imagenesExistentes[] = [];
        $this->imagenes[] = [];
    }

    public function removeItem(int $index): void
    {
        abort_if(count($this->items) <= 1, 422, 'Debe conservar al menos un ítem.');
        unset($this->items[$index], $this->imagenesExistentes[$index], $this->imagenes[$index]);
        $this->items = array_values($this->items);
        $this->imagenesExistentes = array_values($this->imagenesExistentes);
        $this->imagenes = array_values($this->imagenes);
    }

    public function eliminarImagenExistente(int $itemIndex, int $imagenId): void
    {
        $imagen = \App\Models\ItemImagen::whereKey($imagenId)
            ->whereHas('item', fn ($q) => $q->where('tramite_id', $this->tramite->id))
            ->firstOrFail();

        Storage::disk('public')->delete($imagen->nombre_archivo);
        $imagen->delete();

        $this->imagenesExistentes[$itemIndex] = collect($this->imagenesExistentes[$itemIndex] ?? [])
            ->reject(fn ($img) => $img['id'] === $imagenId)
            ->values()
            ->all();
    }

    public function eliminarAdjunto(int $attachmentId): void
    {
        $adjunto = $this->tramite->attachments()->findOrFail($attachmentId);
        \Illuminate\Support\Facades\Storage::disk('public')->delete($adjunto->nombre_archivo);
        $adjunto->delete();
        $this->tramite->refresh()->load('attachments');
    }

    public function save(): void
    {
        abort_unless($this->tramite->creador_id === auth()->id(), 403);
        abort_unless($this->tramite->estado === 'Pendiente de mi revisión', 400);

        $rules = [
            'numeroSecuencial' => 'required|string|max:20',
            'fecha' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.unidad' => 'required|string|max:100',
            'items.*.cantidad' => 'required|numeric|min:0',
            'adjuntos' => 'array|max:10',
            'adjuntos.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ];

        if ($this->tramite->tipo === 'REQ') {
            $rules += [
                'items.*.seccion' => 'required|string|max:100',
                'items.*.descripcion' => 'required|string',
                'items.*.stock' => 'nullable|numeric|min:0',
                'items.*.fecha_requerida' => 'nullable|date',
                'imagenes' => 'array',
                'imagenes.*' => 'array|max:5',
                'imagenes.*.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120',
            ];
        } else {
            $rules += [
                'subtipo' => 'required|in:Proveedor persona natural,Proveedor persona jurídica,Cuarta categoría / Recibo por Honorarios,Planillas',
                'beneficiario' => 'required|string|max:255',
                'items.*.concepto' => 'required|string',
                'items.*.costo' => 'required|numeric|min:0',
            ];
        }

        $this->validate($rules);

        $numero = $this->tramite->tipo === 'SP'
            ? "{$this->anioActual}-{$this->numeroSecuencial}"
            : "{$this->numeroSecuencial}-{$this->anioActual}";

        $modalidadesTexto = collect($this->modalidades)
            ->map(fn ($m) => $m === 'Otro' ? "Otro: {$this->modalidadOtroTexto}" : $m)
            ->implode(', ');

        DB::transaction(function () use ($numero, $modalidadesTexto): void {
            $this->tramite->update([
                'numero' => $numero,
                'fecha' => $this->fecha,
                'subtipo' => $this->tramite->tipo === 'SP' ? $this->subtipo : $this->tramite->subtipo,
                'beneficiario' => $this->tramite->tipo === 'SP' ? $this->beneficiario : $this->tramite->beneficiario,
                'dni_ruc' => $this->dniRuc,
                'modalidad_pago' => $this->tramite->tipo === 'SP' ? ($modalidadesTexto ?: null) : $this->tramite->modalidad_pago,
                'responsable' => $this->responsable,
                'celular' => $this->celular ?: null,
                'observaciones' => $this->observaciones,
            ]);

            $total = 0;
            $idsConservados = [];

            foreach ($this->items as $index => $item) {
                $descripcion = $this->tramite->tipo === 'REQ' ? $item['descripcion'] : $item['concepto'];
                $cantidad = (float) $item['cantidad'];
                $costo = $this->tramite->tipo === 'SP' ? (float) $item['costo'] : null;
                $monto = $costo === null ? null : round($cantidad * $costo, 2);
                $total += $monto ?? 0;

                $datosItem = [
                    'tramite_id' => $this->tramite->id,
                    'seccion' => $this->tramite->tipo === 'REQ' ? $item['seccion'] : 'Solicitud de Pago',
                    'nro' => $index + 1,
                    'descripcion' => $descripcion,
                    'unidad' => $item['unidad'],
                    'cantidad' => $cantidad,
                    'stock' => $this->tramite->tipo === 'REQ' ? (float) ($item['stock'] ?? 0) : 0,
                    'comprar' => $this->tramite->tipo === 'REQ' ? max($cantidad - (float) ($item['stock'] ?? 0), 0) : 0,
                    'justificacion' => $item['justificacion'] ?? null,
                    'costo' => $costo,
                    'monto' => $monto,
                    'prioridad' => $this->tramite->tipo === 'REQ' ? ($item['prioridad'] ?? 'Normal') : 'Normal',
                    'fecha_requerida' => $this->tramite->tipo === 'REQ' && ! empty($item['fecha_requerida']) ? $item['fecha_requerida'] : null,
                    'nro_despacho' => $this->tramite->tipo === 'SP' && ($item['nro_despacho'] ?? '') !== '' ? $item['nro_despacho'] : null,
                ];

                $itemModel = ! empty($item['id']) ? Item::find($item['id']) : null;

                if ($itemModel) {
                    $itemModel->update($datosItem);
                } else {
                    $itemModel = Item::create($datosItem);
                }

                $idsConservados[] = $itemModel->id;

                foreach ($this->imagenes[$index] ?? [] as $imagenNueva) {
                    $itemModel->imagenes()->create([
                        'nombre_original' => $imagenNueva->getClientOriginalName(),
                        'nombre_archivo' => $imagenNueva->store('imagenes-items', 'public'),
                    ]);
                }
            }

            $this->tramite->items()->whereNotIn('id', $idsConservados)->with('imagenes')->get()
                ->each(function (Item $itemEliminado): void {
                    $itemEliminado->imagenes->each(fn ($img) => Storage::disk('public')->delete($img->nombre_archivo));
                    $itemEliminado->delete();
                });

            if ($this->tramite->tipo === 'SP') {
                $this->tramite->update(['abono' => $total]);

                $this->tramite->spCuentas()->delete();
                foreach ($this->cuentas as $cuenta) {
                    if (trim($cuenta['banco'] ?? '') === '' && trim($cuenta['cuenta_cci'] ?? '') === '') {
                        continue;
                    }

                    $this->tramite->spCuentas()->create([
                        'banco' => $cuenta['banco'] === 'Otro' ? $cuenta['bancoOtro'] : $cuenta['banco'],
                        'cuenta_cci' => $cuenta['cuenta_cci'] ?: null,
                    ]);
                }
            }

            foreach ($this->adjuntos as $order => $archivo) {
                $this->tramite->attachments()->create([
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'nombre_archivo' => $archivo->store('adjuntos', 'public'),
                    'orden' => $order,
                ]);
            }

            History::create([
                'tramite_id' => $this->tramite->id,
                'usuario_id' => auth()->id(),
                'accion' => 'Trámite editado por el creador antes de enviarlo a aprobación',
            ]);
        });

        session()->flash('status', 'Trámite actualizado correctamente.');
        $this->redirectRoute('tramites.show', $this->tramite, navigate: true);
    }

    public function render()
    {
        return view('livewire.tramites.edit-request');
    }
}
