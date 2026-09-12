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

    public string $numeroSecuencial = '';
    public int $anioActual = 0;
    public string $fecha;
    public ?int $obra_id = null;

    public bool $tipoEjecucion = false;
    public bool $tipoSeguridad = false;
    public bool $tipoOficina = false;

    public array $items = [];
    public array $imagenes = [];
    public $archivoExterno = null;

    protected string $proyecto = 'MEJORAMIENTO Y AMPLIACIÓN DEL SERVICIO DE TRANSITABILIDAD VIAL INTERURBANA EN EL PUENTE VEHICULAR SOBRE EL RÍO TABACONAS EN LA LOCALIDAD LA VEGA DEL PUENTE DEL DISTRITO DE SAN JOSE DEL ALTO DE LA PROVINCIA DE JAÉN DEL DEPARTAMENTO DE CAJAMARCA';
    protected string $lugar = 'C.P. LA VEGA - DISTRITO SAN JOSE DEL ALTO-PROVINCIA JAEN- REGION CAJAMARCA';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Administración', 'Logística', 'Tesorería', 'Sistemas']), 403);
        $this->fecha = now()->format('Y-m-d');
        $this->anioActual = (int) now()->year;
        $this->obra_id = auth()->user()->obra_activa_id;
    }

    public function getTiposProperty(): array
    {
        return [
            [
                'key' => 'ejecucion',
                'seccion' => 'Ejecución de Obra',
                'icon' => 'building-office-2',
                'titulo' => 'Ejecución de obra',
                'descripcion' => 'Materiales requeridos para ejecución.',
                'activo' => $this->tipoEjecucion,
            ],
            [
                'key' => 'seguridad',
                'seccion' => 'Ing. de Seguridad',
                'icon' => 'shield-check',
                'titulo' => 'Seguridad en obra',
                'descripcion' => 'Materiales e implementos de seguridad.',
                'activo' => $this->tipoSeguridad,
            ],
            [
                'key' => 'oficina',
                'seccion' => 'Útiles de Oficina',
                'icon' => 'paper-clip',
                'titulo' => 'Útiles de oficina',
                'descripcion' => 'Materiales y útiles requeridos para oficina.',
                'activo' => $this->tipoOficina,
            ],
        ];
    }

    public function getCodigoFormatoProperty(): string
    {
        return app(CodigoGeneratorService::class)->formatoF01A($this->numeroSecuencial);
    }

    protected function seccionParaTipo(string $key): string
    {
        return match ($key) {
            'ejecucion' => 'Ejecución de Obra',
            'seguridad' => 'Ing. de Seguridad',
            'oficina' => 'Útiles de Oficina',
            default => '',
        };
    }

    public function toggleTipo(string $key): void
    {
        $propiedad = match ($key) {
            'ejecucion' => 'tipoEjecucion',
            'seguridad' => 'tipoSeguridad',
            'oficina' => 'tipoOficina',
            default => null,
        };

        if ($propiedad === null) {
            return;
        }

        $this->$propiedad = ! $this->$propiedad;
        $seccion = $this->seccionParaTipo($key);

        if ($this->$propiedad) {
            $existe = collect($this->items)->contains(fn ($item) => $item['seccion'] === $seccion);

            if (! $existe) {
                $this->addItemTo($seccion);
            }

            return;
        }

        foreach ($this->items as $index => $item) {
            if ($item['seccion'] === $seccion) {
                unset($this->items[$index], $this->imagenes[$index]);
            }
        }

        $this->items = array_values($this->items);
        $this->imagenes = array_values($this->imagenes);
    }

    public function addItemTo(string $seccion): void
    {
        $this->items[] = [
            'seccion' => $seccion,
            'descripcion' => '',
            'unidad' => '',
            'cantidad' => 0,
            'stock' => 0,
            'justificacion' => '',
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index], $this->imagenes[$index]);
        $this->items = array_values($this->items);
        $this->imagenes = array_values($this->imagenes);
    }

    public function getComprarProperty(): array
    {
        return collect($this->items)->map(
            fn ($item) => max((float) ($item['cantidad'] ?? 0) - (float) ($item['stock'] ?? 0), 0)
        )->all();
    }

    public function save(CodigoGeneratorService $codigos)
    {
        $this->validate([
            'numeroSecuencial' => ['required', 'string', 'max:20', 'regex:/^\d+$/'],
            'fecha' => 'required|date',
            'obra_id' => 'required|exists:obras,id',
            'items' => 'required|array|min:1',
            'items.*.descripcion' => 'required|string',
            'items.*.unidad' => 'required|string',
            'items.*.cantidad' => 'required|numeric|min:0',
            'items.*.seccion' => 'required|string',
            'archivoExterno' => 'nullable|file|mimes:pdf,xls,xlsx,xlsm|max:10240',
            'imagenes' => 'array',
            'imagenes.*' => 'array|max:5',
            'imagenes.*.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'numeroSecuencial.regex' => 'El número de requerimiento solo debe contener dígitos.',
        ]);

        abort_unless(auth()->user()->hasAccessToObra($this->obra_id), 403);

        $user = auth()->user();
        $esRolDeOficina = $user->hasAnyRole(['Administración', 'Logística', 'Tesorería', 'Sistemas'])
            && ! $user->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento']);

        if ($esRolDeOficina) {
            $seccionesUsadas = collect($this->items)
                ->filter(fn ($item) => trim($item['descripcion']) !== '')
                ->pluck('seccion')
                ->unique();

            if ($seccionesUsadas->contains(fn ($seccion) => $seccion !== 'Útiles de Oficina')) {
                $this->addError('items', 'Su rol solo puede registrar requerimientos de Útiles de oficina.');

                return;
            }
        }

        $numero = "{$this->numeroSecuencial}-{$this->anioActual}";

        $duplicado = Tramite::where('obra_id', $this->obra_id)
            ->where('tipo', 'REQ')
            ->whereRaw('LOWER(TRIM(numero)) = ?', [mb_strtolower(trim($numero))])
            ->exists();

        if ($duplicado) {
            $this->addError('numeroSecuencial', "Ya existe un requerimiento con el número {$numero} en esta obra.");

            return;
        }

        $obra = \App\Models\Obra::findOrFail($this->obra_id);

        $aprobadores = [
            'Control y Planeamiento' => User::role('Control y Planeamiento')->activeAssignedToObra($obra->id)->first(),
            'Gerencia de Obra' => User::role('Gerencia de Obra')->activeAssignedToObra($obra->id)->first(),
        ];

        foreach ($aprobadores as $rol => $usuario) {
            if (! $usuario) {
                $this->addError('numeroSecuencial', "No hay un usuario activo con el rol {$rol} asignado a esta obra. No se puede registrar el requerimiento.");

                return;
            }
        }

        $tid = DB::transaction(function () use ($codigos, $numero, $obra, $aprobadores) {
            $year = substr($this->fecha, 0, 4);
            $tracking = $codigos->nextTracking('REQ', $year, $obra->codigo);
            $formato = $codigos->formatoF01A($numero);

            $tramite = Tramite::create([
                'tracking' => $tracking,
                'obra_id' => $obra->id,
                'tipo' => 'REQ',
                'numero' => $numero,
                'fecha' => $this->fecha,
                'proyecto' => $obra->proyecto ?: $obra->nombre,
                'lugar' => $obra->lugar ?: $obra->nombre,
                'estado' => 'Pendiente de mi revisión',
                'formato' => $formato,
                'creador_id' => auth()->id(),
            ]);

            $contadorSeccion = [];

            foreach ($this->items as $index => $item) {
                if (trim($item['descripcion']) === '') {
                    continue;
                }

                $seccion = $item['seccion'];
                $contadorSeccion[$seccion] = ($contadorSeccion[$seccion] ?? 0) + 1;

                $cantidad = (float) $item['cantidad'];
                $stock = (float) $item['stock'];

                $itemModel = Item::create([
                    'tramite_id' => $tramite->id,
                    'seccion' => $seccion,
                    'nro' => $contadorSeccion[$seccion],
                    'descripcion' => $item['descripcion'],
                    'unidad' => $item['unidad'],
                    'cantidad' => $cantidad,
                    'stock' => $stock,
                    'comprar' => max($cantidad - $stock, 0),
                    'justificacion' => $item['justificacion'] ?? null,
                ]);

                foreach ($this->imagenes[$index] ?? [] as $imagen) {
                    $itemModel->imagenes()->create([
                        'nombre_original' => $imagen->getClientOriginalName(),
                        'nombre_archivo' => $imagen->store('imagenes-items', 'public'),
                    ]);
                }
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
                'accion' => "Requerimiento registrado: {$tracking}",
            ]);

            if ($this->archivoExterno) {
                Attachment::create([
                    'tramite_id' => $tramite->id,
                    'nombre_original' => $this->archivoExterno->getClientOriginalName(),
                    'nombre_archivo' => $this->archivoExterno->store('adjuntos', 'public'),
                    'orden' => 0,
                ]);
            }

            return $tramite->id;
        });

        session()->flash('status', 'Requerimiento registrado correctamente.');

        return $this->redirect('/dashboard', navigate: true);
    }

    protected function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'min' => 'El campo :attribute debe ser mayor o igual a :min.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'max' => 'El campo :attribute no debe superar :max caracteres.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'numeroSecuencial' => 'número de requerimiento',
            'fecha' => 'fecha',
            'items' => 'ítems',
            'items.*.descripcion' => 'descripción',
            'items.*.unidad' => 'unidad',
            'items.*.cantidad' => 'cantidad',
            'items.*.seccion' => 'sección',
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

        <flux:heading size="xl" class="mt-2 text-[#142f44]">Requerimiento de materiales</flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            Registre la información del requerimiento. Primero se guardará para su revisión y, después de su V.°B.°, continuará con los responsables correspondientes.
        </flux:text>
    </div>

    <form wire:submit="save">
        <div class="rounded-xl border border-t-4 border-zinc-200 border-t-blue-600 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <!-- Datos generales -->
            <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                <div class="mb-3 text-sm font-semibold text-[#142f44] dark:text-white">Datos generales</div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <flux:label>N° de requerimiento <span class="text-red-500">*</span></flux:label>
                        <div class="mt-1 flex items-stretch overflow-hidden rounded-lg border border-zinc-200 focus-within:border-[#142f44] dark:border-zinc-700">
                            <input
                                type="text"
                                inputmode="numeric"
                                wire:model="numeroSecuencial"
                                required
                                placeholder="Ingrese número de requerimiento"
                                class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm text-zinc-700 placeholder-zinc-400 focus:ring-0 dark:text-zinc-200"
                            />
                            <span class="flex items-center px-1 text-zinc-300">-</span>
                            <span class="flex items-center bg-zinc-50 px-3 text-sm font-semibold text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                                {{ $anioActual }}
                            </span>
                        </div>
                        @error('numeroSecuencial') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
                    </div>

                    <flux:input type="date" label="Fecha" wire:model="fecha" required />
                </div>
            </div>

            <!-- Tipo de requerimiento -->
            <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                <div class="mb-3 text-sm font-semibold text-[#142f44] dark:text-white">Tipo de requerimiento</div>

                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($this->tipos as $tipoInfo)
                        <button
                            type="button"
                            wire:click="toggleTipo('{{ $tipoInfo['key'] }}')"
                            @class([
                                'relative flex items-start gap-3 rounded-xl border p-3.5 text-left transition',
                                'border-[#142f44] bg-blue-50/60 dark:bg-blue-950/20' => $tipoInfo['activo'],
                                'border-zinc-200 bg-white hover:border-[#142f44]/30 dark:border-zinc-700 dark:bg-zinc-800' => ! $tipoInfo['activo'],
                            ])
                        >
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400">
                                <flux:icon :icon="$tipoInfo['icon']" class="size-4" />
                            </div>

                            <div class="min-w-0">
                                <div class="font-semibold text-[#142f44] dark:text-white">{{ $tipoInfo['titulo'] }}</div>
                                <div class="mt-0.5 text-xs text-zinc-500">{{ $tipoInfo['descripcion'] }}</div>
                            </div>

                            @if ($tipoInfo['activo'])
                                <flux:icon.check-circle class="absolute top-2.5 end-2.5 size-4 text-[#142f44] dark:text-white" />
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Ítems por sección -->
            @foreach ($this->tipos as $tipoInfo)
                @continue(! $tipoInfo['activo'])

                <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="text-sm font-semibold text-[#142f44] dark:text-white">{{ $tipoInfo['titulo'] }}</div>
                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="addItemTo('{{ $tipoInfo['seccion'] }}')">
                            Agregar ítem
                        </flux:button>
                    </div>

                    <div class="space-y-3">
                        @foreach ($items as $index => $item)
                            @continue($item['seccion'] !== $tipoInfo['seccion'])

                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="mb-3 flex items-center justify-between">
                                    <flux:badge>Ítem {{ $index + 1 }}</flux:badge>
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="removeItem({{ $index }})" />
                                </div>

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div class="sm:col-span-3">
                                        <flux:textarea label="Descripción" wire:model="items.{{ $index }}.descripcion" rows="2" />
                                    </div>

                                    <flux:input label="Unidad" wire:model="items.{{ $index }}.unidad" placeholder="Ej: und, m3, kg" />
                                    <flux:input type="number" step="0.01" label="Cantidad requerida" wire:model.live="items.{{ $index }}.cantidad" />
                                    <flux:input type="number" step="0.01" label="Stock disponible" wire:model.live="items.{{ $index }}.stock" />

                                    <div class="sm:col-span-3">
                                        <flux:textarea label="Justificación" wire:model="items.{{ $index }}.justificacion" rows="2" />
                                    </div>

                                    <div class="sm:col-span-3">
                                        <flux:label>Imágenes de referencia</flux:label>
                                        <input type="file" wire:model="imagenes.{{ $index }}" multiple accept=".jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-sm" />
                                        @error("imagenes.{$index}.*") <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
                                    </div>

                                    <div class="sm:col-span-3 text-sm text-zinc-500">
                                        Cantidad a comprar: <span class="font-semibold text-zinc-900 dark:text-white">{{ $this->comprar[$index] ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @error('items') <div class="px-5 pt-4"><flux:text class="text-red-500">{{ $message }}</flux:text></div> @enderror

            <!-- Archivo externo -->
            <div class="border-b border-zinc-100 p-5 dark:border-zinc-700">
                <div class="mb-3 flex items-center gap-1.5 text-sm font-semibold text-[#142f44] dark:text-white">
                    Archivo externo del requerimiento
                    <span class="text-xs font-normal text-zinc-400">(opcional)</span>
                </div>

                <flux:label>Adjuntar copia en PDF o Excel</flux:label>
                <label class="mt-1 flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-dashed border-zinc-300 px-4 py-3 text-sm transition hover:border-[#142f44]/40 dark:border-zinc-600">
                    <span class="flex min-w-0 items-center gap-2 font-medium text-[#142f44] dark:text-white">
                        <flux:icon.paper-clip class="size-4 shrink-0 text-zinc-400" />
                        <span class="truncate">{{ $archivoExterno ? $archivoExterno->getClientOriginalName() : 'Adjuntar archivo' }}</span>
                    </span>
                    <span class="shrink-0 text-xs text-zinc-400">PDF o Excel</span>
                    <input type="file" wire:model="archivoExterno" accept=".pdf,.xls,.xlsx,.xlsm" class="hidden" />
                </label>
                @error('archivoExterno') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror

                <flux:text class="mt-2 text-xs text-zinc-500">
                    Opcional. Puede adjuntar un solo requerimiento externo en PDF o Excel. Queda como respaldo y no se integra al PDF generado por LANR.
                </flux:text>
            </div>

            <!-- Identificación del trámite -->
            <div class="p-5">
                <div class="mb-3 text-sm font-semibold text-[#142f44] dark:text-white">Identificación del trámite</div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <flux:label>Código de formato</flux:label>
                        <flux:input :value="$this->codigoFormato" readonly class="mt-1 bg-zinc-50 dark:bg-zinc-900" />
                    </div>

                    <div>
                        <flux:label>Código de seguimiento</flux:label>
                        <flux:input value="Se genera automáticamente al registrar" readonly class="mt-1 bg-zinc-50 dark:bg-zinc-900" />
                    </div>

                    <div class="flex items-start gap-2 rounded-lg bg-blue-50 p-3 text-sm dark:bg-blue-950/30">
                        <flux:icon.information-circle class="mt-0.5 size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                        <div>
                            <div class="font-semibold text-[#142f44] dark:text-white">Primero se guardará para su revisión</div>
                            <div class="text-zinc-500">Podrá revisar, modificar y otorgar su V.°B.° antes de enviarlo al siguiente responsable.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex justify-end gap-3">
            <flux:button variant="ghost" :href="route('tramites.create')" wire:navigate>Cancelar</flux:button>
            <flux:button type="submit" variant="primary" icon="document-check" class="!bg-[#142f44] hover:!bg-[#0d2032]">
                Guardar requerimiento
            </flux:button>
        </div>
    </form>
</div>
