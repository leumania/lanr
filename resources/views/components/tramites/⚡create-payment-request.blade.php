<?php

use App\Models\Approval;
use App\Models\Attachment;
use App\Models\History;
use App\Models\Item;
use App\Models\Tramite;
use App\Models\User;
use App\Services\CodigoGeneratorService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    const SUBTIPOS = [
        'Proveedor persona natural',
        'Proveedor persona jurídica',
        'Cuarta categoría / Recibo por Honorarios',
        'Planillas',
    ];

    const MODALIDADES = ['Transferencia', 'Depósito', 'En efectivo', 'Transferencias varias', 'Otro'];

    const BANCOS = ['Banco de la Nación', 'BBVA', 'BCP', 'Caja Trujillo', 'Otro'];

    const TIPOS_COMPROBANTE = ['Factura', 'RH', 'SC', 'Boleta', 'Otro'];

    public string $subtipo = '';
    public string $numeroSecuencial = '';
    public int $anioActual = 0;
    public string $fecha;
    public ?int $obra_id = null;
    public string $moneda = 'PEN';

    public string $beneficiario = '';
    public string $dni_ruc = '';
    public string $responsable = '';
    public string $celular = '';
    public string $fecha_limite_pago = '';

    public array $modalidades = [];
    public string $modalidadOtroTexto = '';

    public array $cuentas = [];
    public array $items = [];

    public bool $usarAmortizacion = false;
    public string $amortizacion = '';

    public array $comprobantes = [];
    public array $otrosDocumentos = [];

    public string $observaciones = '';

    protected string $proyecto = 'MEJORAMIENTO Y AMPLIACIÓN DEL SERVICIO DE TRANSITABILIDAD VIAL INTERURBANA EN EL PUENTE VEHICULAR SOBRE EL RÍO TABACONAS EN LA LOCALIDAD LA VEGA DEL PUENTE DEL DISTRITO DE SAN JOSE DEL ALTO DE LA PROVINCIA DE JAÉN DEL DEPARTAMENTO DE CAJAMARCA';
    protected string $lugar = 'C.P. LA VEGA - DISTRITO SAN JOSE DEL ALTO-PROVINCIA JAEN- REGION CAJAMARCA';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Contabilidad', 'Administración']), 403);
        $this->fecha = now()->format('Y-m-d');
        $this->anioActual = (int) now()->year;
        $this->obra_id = auth()->user()->obra_activa_id;
        $this->addCuenta();
        $this->addItem();
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
        $this->items[] = ['concepto' => '', 'unidad' => '', 'cantidad' => 1, 'costo' => 0, 'nro_despacho' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function addComprobante(): void
    {
        $this->comprobantes[] = ['tipo' => '', 'numero' => '', 'archivo' => null];
    }

    public function removeComprobante(int $index): void
    {
        unset($this->comprobantes[$index]);
        $this->comprobantes = array_values($this->comprobantes);
    }

    public function getMontosProperty(): array
    {
        return collect($this->items)->map(
            fn ($item) => round((float) ($item['cantidad'] ?? 0) * (float) ($item['costo'] ?? 0), 2)
        )->all();
    }

    public function getTotalProperty(): float
    {
        return array_sum($this->montos);
    }

    public function getSaldoPendienteProperty(): float
    {
        return round($this->total - (float) ($this->amortizacion ?: 0), 2);
    }

    public function getMonedaSimboloProperty(): string
    {
        return $this->moneda === 'USD' ? 'US$' : 'S/';
    }

    public function save(CodigoGeneratorService $codigos)
    {
        $this->validate([
            'subtipo' => 'required|in:'.implode(',', self::SUBTIPOS),
            'numeroSecuencial' => ['required', 'string', 'max:20', 'regex:/^\d+$/'],
            'fecha' => 'required|date',
            'moneda' => 'required|in:PEN,USD',
            'obra_id' => 'required|exists:obras,id',
            'beneficiario' => 'required|string|max:255',
            'dni_ruc' => 'nullable|string|max:20',
            'responsable' => 'nullable|string|max:150',
            'celular' => 'nullable|string|max:20',
            'fecha_limite_pago' => 'nullable|date',
            'cuentas.*.banco' => 'nullable|string|max:100',
            'cuentas.*.bancoOtro' => 'nullable|string|max:100',
            'cuentas.*.cuenta_cci' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.concepto' => 'required|string',
            'items.*.unidad' => 'required|string',
            'items.*.cantidad' => 'required|numeric',
            'items.*.costo' => 'required|numeric|min:0',
            'items.*.nro_despacho' => 'nullable|string|max:50',
            'amortizacion' => 'nullable|numeric|min:0',
            'comprobantes.*.tipo' => 'nullable|string|max:50',
            'comprobantes.*.numero' => 'nullable|string|max:100',
            'comprobantes.*.archivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'otrosDocumentos' => 'array|max:10',
            'otrosDocumentos.*' => 'file|mimes:pdf|max:10240',
            'observaciones' => 'nullable|string',
        ], [
            'numeroSecuencial.regex' => 'El número de solicitud solo debe contener dígitos.',
        ], [
            'numeroSecuencial' => 'número de solicitud',
            'subtipo' => 'tipo de solicitud',
        ]);

        if (in_array('Otro', $this->modalidades, true) && trim($this->modalidadOtroTexto) === '') {
            $this->addError('modalidadOtroTexto', 'Especifica la modalidad de pago.');

            return;
        }

        $totalPreview = collect($this->items)->sum(
            fn ($item) => (float) ($item['cantidad'] ?? 0) * (float) ($item['costo'] ?? 0)
        );

        if ($totalPreview <= 0) {
            $this->addError('items', 'La solicitud debe tener al menos un concepto con cantidad y costo mayores a 0.');

            return;
        }

        if ($this->usarAmortizacion && (float) ($this->amortizacion ?: 0) > $totalPreview) {
            $this->addError('amortizacion', 'La amortización no puede superar el total de la solicitud.');

            return;
        }

        abort_unless(auth()->user()->hasAccessToObra($this->obra_id), 403);

        $numero = "{$this->anioActual}-{$this->numeroSecuencial}";

        if (Tramite::where('obra_id', $this->obra_id)->where('tipo', 'SP')->where('numero', $numero)->exists()) {
            $this->addError('numeroSecuencial', 'Ya existe una solicitud de pago con este número.');

            return;
        }

        $modalidadesTexto = collect($this->modalidades)
            ->map(fn ($m) => $m === 'Otro' ? "Otro: {$this->modalidadOtroTexto}" : $m)
            ->implode(', ');

        $primeraCuenta = collect($this->cuentas)->first(fn ($c) => trim($c['banco'] ?? '') !== '' || trim($c['cuenta_cci'] ?? '') !== '');

        $obra = \App\Models\Obra::findOrFail($this->obra_id);

        $aprobadores = [
            'Logística' => User::role('Logística')->activeAssignedToObra($obra->id)->first(),
            'Administración' => User::role('Administración')->activeAssignedToObra($obra->id)->first(),
        ];

        foreach ($aprobadores as $rol => $usuario) {
            if (! $usuario) {
                $this->addError('numeroSecuencial', "No hay un usuario activo con el rol {$rol} asignado a esta obra. No se puede registrar la solicitud.");

                return;
            }
        }

        DB::transaction(function () use ($codigos, $numero, $modalidadesTexto, $primeraCuenta, $obra, $aprobadores) {
            $year = substr($this->fecha, 0, 4);
            $tracking = $codigos->nextTracking('SP', $year, $obra->codigo);

            $observaciones = $this->observaciones;
            if ($this->usarAmortizacion) {
                $simbolo = $this->monedaSimbolo;
                $observaciones = trim($observaciones."\n\nAmortización registrada: {$simbolo} ".number_format((float) ($this->amortizacion ?: 0), 2).'. Saldo pendiente: '.$simbolo.' '.number_format($this->saldoPendiente, 2).'.');
            }

            $tramite = Tramite::create([
                'tracking' => $tracking,
                'obra_id' => $obra->id,
                'tipo' => 'SP',
                'subtipo' => $this->subtipo,
                'numero' => $numero,
                'fecha' => $this->fecha,
                'proyecto' => $obra->proyecto ?: $obra->nombre,
                'lugar' => $obra->lugar ?: $obra->nombre,
                'beneficiario' => $this->beneficiario,
                'dni_ruc' => $this->dni_ruc,
                'modalidad_pago' => $modalidadesTexto ?: null,
                'responsable' => $this->responsable ?: null,
                'celular' => $this->celular ?: null,
                'moneda' => $this->moneda,
                'fecha_limite_pago' => $this->fecha_limite_pago ?: null,
                'banco' => $primeraCuenta ? ($primeraCuenta['banco'] === 'Otro' ? $primeraCuenta['bancoOtro'] : $primeraCuenta['banco']) : null,
                'cuenta_cci' => $primeraCuenta['cuenta_cci'] ?? null,
                'abono' => 0,
                'observaciones' => $observaciones ?: null,
                'estado' => 'Pendiente de mi revisión',
                'creador_id' => auth()->id(),
            ]);

            $totalSolicitud = 0;

            foreach ($this->items as $i => $item) {
                if (trim($item['concepto']) === '') {
                    continue;
                }

                $cantidad = (float) $item['cantidad'];
                $costo = (float) $item['costo'];
                $monto = round($cantidad * $costo, 2);
                $totalSolicitud += $monto;

                Item::create([
                    'tramite_id' => $tramite->id,
                    'seccion' => 'Solicitud de Pago',
                    'nro' => $i + 1,
                    'descripcion' => $item['concepto'],
                    'unidad' => $item['unidad'],
                    'cantidad' => $cantidad,
                    'costo' => $costo,
                    'monto' => $monto,
                    'nro_despacho' => $item['nro_despacho'] !== '' ? $item['nro_despacho'] : null,
                ]);
            }

            $tramite->update(['abono' => $totalSolicitud]);

            foreach ($this->modalidades as $nombre) {
                $tramite->spModalidades()->create([
                    'nombre' => $nombre === 'Otro' ? "Otro: {$this->modalidadOtroTexto}" : $nombre,
                ]);
            }

            foreach ($this->cuentas as $cuenta) {
                if (trim($cuenta['banco'] ?? '') === '' && trim($cuenta['cuenta_cci'] ?? '') === '') {
                    continue;
                }

                $tramite->spCuentas()->create([
                    'banco' => $cuenta['banco'] === 'Otro' ? $cuenta['bancoOtro'] : $cuenta['banco'],
                    'cuenta_cci' => $cuenta['cuenta_cci'] ?: null,
                ]);
            }

            foreach ($this->comprobantes as $comprobante) {
                if (trim($comprobante['tipo'] ?? '') === '' && trim($comprobante['numero'] ?? '') === '' && ! $comprobante['archivo']) {
                    continue;
                }

                $tramite->spComprobantes()->create([
                    'tipo' => $comprobante['tipo'] ?: null,
                    'numero' => $comprobante['numero'] ?: null,
                ]);

                if ($comprobante['archivo']) {
                    Attachment::create([
                        'tramite_id' => $tramite->id,
                        'nombre_original' => $comprobante['archivo']->getClientOriginalName(),
                        'nombre_archivo' => $comprobante['archivo']->store('adjuntos', 'public'),
                        'orden' => 0,
                    ]);
                }
            }

            foreach ($this->otrosDocumentos as $orden => $archivo) {
                Attachment::create([
                    'tramite_id' => $tramite->id,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'nombre_archivo' => $archivo->store('adjuntos', 'public'),
                    'orden' => $orden + 1,
                ]);
            }

            foreach ($aprobadores as $rol => $usuario) {
                if ($usuario) {
                    Approval::create([
                        'tramite_id' => $tramite->id,
                        'rol' => $rol,
                        'usuario_id' => $usuario->id,
                    ]);
                }
            }

            History::create([
                'tramite_id' => $tramite->id,
                'usuario_id' => auth()->id(),
                'accion' => "Solicitud registrada: {$tracking}",
            ]);
        });

        session()->flash('status', 'Solicitud de pago registrada correctamente.');

        return $this->redirect(route('dashboard'), navigate: true);
    }

    protected function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'min' => 'El campo :attribute debe ser mayor o igual a :min.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'in' => 'El valor seleccionado para :attribute no es válido.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
        ];
    }
};
?>
<div class="space-y-4">
    <div>
        <flux:link :href="route('tramites.create')" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-zinc-500 hover:text-[#142f44] dark:hover:text-white">
            <flux:icon.arrow-left class="size-4" />
            Nuevo trámite
        </flux:link>

        <flux:heading size="xl" class="mt-2 text-[#142f44]">Solicitud de pago</flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            Registre la solicitud. Primero quedará en su revisión antes de pasar al flujo de vistos buenos.
        </flux:text>
    </div>

    <form wire:submit="save">
        <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <!-- Datos de la solicitud -->
            <div class="flex items-start gap-3 border-b border-zinc-100 p-5 dark:border-zinc-700">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400">
                    <flux:icon.credit-card class="size-5" />
                </div>
                <div>
                    <div class="text-base font-semibold text-[#142f44] dark:text-white">Datos de la solicitud</div>
                    <div class="text-sm text-zinc-500">Los campos opcionales vacíos se guardan sin texto y no se mostrarán en el PDF.</div>
                </div>
            </div>

            <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select label="Tipo de solicitud" wire:model="subtipo" required>
                        <flux:select.option value="">Seleccione...</flux:select.option>
                        @foreach (self::SUBTIPOS as $opcion)
                            <flux:select.option :value="$opcion">{{ $opcion }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div>
                        <flux:label>N° <span class="text-red-500">*</span></flux:label>
                        <div class="mt-1 flex items-stretch overflow-hidden rounded-lg border border-zinc-200 focus-within:border-[#142f44] dark:border-zinc-700">
                            <span class="flex items-center bg-zinc-50 px-3 text-sm font-semibold text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                                {{ $anioActual }}
                            </span>
                            <span class="flex items-center px-1 text-zinc-300">-</span>
                            <input
                                type="text"
                                inputmode="numeric"
                                wire:model="numeroSecuencial"
                                required
                                placeholder="N°"
                                class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 dark:text-zinc-200"
                            />
                        </div>
                        @error('numeroSecuencial') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
                    </div>

                    <flux:input type="date" label="Fecha" wire:model="fecha" required />

                    <flux:select label="Moneda" wire:model="moneda" required>
                        <flux:select.option value="PEN">Soles (S/)</flux:select.option>
                        <flux:select.option value="USD">Dólares (US$)</flux:select.option>
                    </flux:select>

                    <div class="sm:col-span-2">
                        <flux:input label="Beneficiario" wire:model="beneficiario" placeholder="Nombre, apellidos o razón social" required />
                    </div>

                    <flux:input label="DNI / RUC" wire:model="dni_ruc" placeholder="Opcional" />
                    <flux:input label="Responsable" wire:model="responsable" placeholder="Opcional" />
                    <flux:input label="N° celular" wire:model="celular" placeholder="Opcional" />

                    <div>
                        <flux:input type="date" label="Fecha límite de pago" wire:model="fecha_limite_pago" />
                        <flux:text class="mt-1 text-xs text-zinc-500">Opcional. Útil para pagos programados o con vencimiento.</flux:text>
                    </div>
                </div>
            </div>

            <!-- Modalidad de pago -->
            <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-[#142f44] dark:text-white">Modalidad de pago</div>
                    <div class="text-xs text-zinc-400">Opcional · Puede marcar más de una.</div>
                </div>

                <div class="flex flex-wrap gap-3">
                    @foreach (self::MODALIDADES as $modalidad)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 px-3.5 py-2 text-sm dark:border-zinc-700">
                            <input
                                type="checkbox"
                                wire:click="toggleModalidad('{{ $modalidad }}')"
                                @checked(in_array($modalidad, $modalidades, true))
                                class="rounded border-zinc-300 text-[#142f44] focus:ring-[#142f44]"
                            />
                            {{ $modalidad }}
                        </label>
                    @endforeach
                </div>

                @if (in_array('Otro', $modalidades, true))
                    <div class="mt-3">
                        <flux:input label="Otra modalidad" wire:model="modalidadOtroTexto" placeholder="Especifica la modalidad" />
                        @error('modalidadOtroTexto') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
                    </div>
                @endif
            </div>

            <!-- Banco y cuenta / CCI -->
            <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-[#142f44] dark:text-white">Banco y cuenta / CCI</div>
                    <div class="text-xs text-zinc-400">Opcional · Cada cuenta queda asociada a su banco.</div>
                </div>

                <div class="space-y-3">
                    @foreach ($cuentas as $index => $cuenta)
                        <div class="flex items-start gap-3">
                            <div class="grid flex-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <flux:select label="Banco" wire:model.live="cuentas.{{ $index }}.banco">
                                        <flux:select.option value="">Seleccione...</flux:select.option>
                                        @foreach (self::BANCOS as $bancoOpcion)
                                            <flux:select.option :value="$bancoOpcion">{{ $bancoOpcion }}</flux:select.option>
                                        @endforeach
                                    </flux:select>

                                    @if (($cuenta['banco'] ?? '') === 'Otro')
                                        <flux:input class="mt-2" wire:model="cuentas.{{ $index }}.bancoOtro" placeholder="Otro banco" />
                                    @endif
                                </div>

                                <flux:input label="Cuenta / CCI" wire:model="cuentas.{{ $index }}.cuenta_cci" placeholder="Opcional" />
                            </div>

                            <flux:button class="mt-6" variant="ghost" size="sm" icon="trash" wire:click="removeCuenta({{ $index }})" />
                        </div>
                    @endforeach
                </div>

                <flux:button class="mt-3" variant="ghost" size="sm" icon="plus" wire:click="addCuenta">
                    Agregar banco / cuenta
                </flux:button>
            </div>

            <!-- Detalle -->
            <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                <div class="mb-3">
                    <div class="text-sm font-semibold text-[#142f44] dark:text-white">Detalle</div>
                    <div class="text-xs text-zinc-500">Cantidad y costo unitario son obligatorios. Se permiten cantidades negativas; en el PDF se resaltarán.</div>
                </div>

                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="bg-[#142f44] text-xs text-white uppercase">
                                <th class="px-3 py-2 font-medium">N°</th>
                                <th class="px-3 py-2 font-medium">Concepto</th>
                                <th class="px-3 py-2 font-medium">Unidad</th>
                                <th class="px-3 py-2 font-medium">Cantidad</th>
                                <th class="px-3 py-2 font-medium">Costo unit.</th>
                                <th class="px-3 py-2 font-medium">Monto</th>
                                <th class="px-3 py-2 font-medium">Detalles</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($items as $index => $item)
                                <tr x-data="{ detalles: false }">
                                    <td class="px-3 py-2 align-top text-zinc-500">{{ $index + 1 }}</td>
                                    <td class="px-3 py-2 align-top"><flux:input wire:model="items.{{ $index }}.concepto" placeholder="Concepto" /></td>
                                    <td class="px-3 py-2 align-top"><flux:input wire:model="items.{{ $index }}.unidad" placeholder="Ej: día, servicio" /></td>
                                    <td class="px-3 py-2 align-top"><flux:input type="number" step="0.01" wire:model.live="items.{{ $index }}.cantidad" /></td>
                                    <td class="px-3 py-2 align-top"><flux:input type="number" step="0.01" wire:model.live="items.{{ $index }}.costo" /></td>
                                    <td class="px-3 py-2 align-top font-semibold whitespace-nowrap text-[#142f44] dark:text-white">
                                        {{ $this->monedaSimbolo }} {{ number_format($this->montos[$index] ?? 0, 2) }}
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <div class="flex items-center gap-1">
                                            <flux:button type="button" size="sm" variant="ghost" x-on:click="detalles = ! detalles">Detalles</flux:button>
                                            @if (count($items) > 1)
                                                <flux:button variant="ghost" size="sm" icon="trash" wire:click="removeItem({{ $index }})" />
                                            @endif
                                        </div>
                                        <div x-show="detalles" x-cloak class="mt-2 w-40">
                                            <flux:input size="sm" wire:model="items.{{ $index }}.nro_despacho" placeholder="N° despacho (opcional)" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <flux:button class="mt-3" variant="ghost" size="sm" icon="plus" wire:click="addItem">
                    Agregar ítem
                </flux:button>

                @error('items') <flux:text class="mt-2 block text-red-500">{{ $message }}</flux:text> @enderror

                <div class="mt-3 flex items-center justify-end gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-700">
                    <flux:text class="text-zinc-500">Total</flux:text>
                    <flux:heading size="lg" class="text-[#142f44] dark:text-white">{{ $this->monedaSimbolo }} {{ number_format($this->total, 2) }}</flux:heading>
                </div>
            </div>

            <!-- Amortización -->
            <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                <label class="flex cursor-pointer items-center gap-2 font-semibold text-[#142f44] dark:text-white">
                    <input type="checkbox" wire:model.live="usarAmortizacion" class="rounded border-zinc-300 text-[#142f44] focus:ring-[#142f44]" />
                    Registrar Solicitud de Pago (amortización)
                </label>

                @if ($usarAmortizacion)
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <flux:input type="number" step="0.01" min="0" label="Monto de amortización" wire:model.live="amortizacion" />
                        <div>
                            <flux:label>Saldo pendiente</flux:label>
                            <flux:input :value="$this->monedaSimbolo.' '.number_format($this->saldoPendiente, 2)" readonly class="mt-1 bg-zinc-50 dark:bg-zinc-900" />
                        </div>
                        @error('amortizacion') <flux:text class="text-sm text-red-500 sm:col-span-2">{{ $message }}</flux:text> @enderror
                    </div>
                @endif
            </div>

            <!-- Comprobantes -->
            <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-[#142f44] dark:text-white">Comprobantes</div>
                    <div class="text-xs text-zinc-400">Opcional · Puede registrar uno o varios.</div>
                </div>

                <div class="space-y-3">
                    @foreach ($comprobantes as $index => $comprobante)
                        <div class="flex items-start gap-3">
                            <div class="grid flex-1 gap-3 sm:grid-cols-3">
                                <flux:select label="Tipo" wire:model="comprobantes.{{ $index }}.tipo">
                                    <flux:select.option value="">Sin seleccionar</flux:select.option>
                                    @foreach (self::TIPOS_COMPROBANTE as $tipoOpcion)
                                        <flux:select.option :value="$tipoOpcion">{{ $tipoOpcion }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:input label="N° comprobante" wire:model="comprobantes.{{ $index }}.numero" placeholder="Número o PENDIENTE" />

                                <div>
                                    <flux:label>Archivo</flux:label>
                                    <input type="file" wire:model="comprobantes.{{ $index }}.archivo" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-sm" />
                                    @error("comprobantes.{$index}.archivo") <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
                                </div>
                            </div>

                            <flux:button class="mt-6" variant="ghost" size="sm" icon="trash" wire:click="removeComprobante({{ $index }})" />
                        </div>
                    @endforeach
                </div>

                <flux:button class="mt-3" variant="ghost" size="sm" icon="plus" wire:click="addComprobante">
                    Agregar comprobante
                </flux:button>
            </div>

            <!-- Observaciones -->
            <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                <flux:textarea label="Observaciones" wire:model="observaciones" rows="2" placeholder="Opcional" />
            </div>

            <!-- Otros documentos -->
            <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm font-semibold text-[#142f44] dark:text-white">Otros documentos de sustento</div>
                    <div class="text-xs text-zinc-400">Opcional · Se anexarán después de los comprobantes en el PDF.</div>
                </div>

                <input type="file" wire:model="otrosDocumentos" multiple accept=".pdf" class="block w-full text-sm text-zinc-700 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-900 file:px-3 file:py-2 file:text-sm file:text-white dark:text-zinc-300 dark:file:bg-white dark:file:text-zinc-900" />
                @error('otrosDocumentos.*') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
            </div>

            <!-- Aviso -->
            <div class="p-5">
                <div class="flex items-start gap-2 rounded-lg bg-blue-50 p-3 text-sm dark:bg-blue-950/30">
                    <flux:icon.information-circle class="mt-0.5 size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                    <div>
                        <div class="font-semibold text-[#142f44] dark:text-white">Primero se guardará para su revisión</div>
                        <div class="text-zinc-500">Podrá leerlo, modificarlo y dar su V.°B.° antes de enviarlo.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex justify-end gap-3">
            <flux:button variant="ghost" :href="route('tramites.create')" wire:navigate>Cancelar</flux:button>
            <flux:button type="submit" variant="primary" icon="arrow-right" class="!bg-[#142f44] hover:!bg-[#0d2032]">
                Registrar y revisar
            </flux:button>
        </div>
    </form>
</div>
