<?php

use App\Models\Approval;
use App\Models\Attachment;
use App\Models\History;
use App\Models\Item;
use App\Models\Tramite;
use App\Models\UnidadCatalogo;
use App\Models\User;
use App\Services\CodigoGeneratorService;
use App\Support\TramiteRoles;
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
        abort_unless(auth()->user()->hasAnyRole(TramiteRoles::requirementCreatorRoles()), 403);
        $this->fecha = now()->format('Y-m-d');
        $this->anioActual = (int) now()->year;
        $this->obra_id = auth()->user()->obra_activa_id;
        $this->numeroSecuencial = $this->siguienteNumero();
    }

    protected function siguienteNumero(): string
    {
        $ultimo = 0;

        Tramite::where('obra_id', $this->obra_id)
            ->where('tipo', 'REQ')
            ->pluck('numero')
            ->each(function (string $numero) use (&$ultimo): void {
                if (preg_match('/^(\d+)-(\d{4})$/', trim($numero), $m) && (int) $m[2] === $this->anioActual) {
                    $ultimo = max($ultimo, (int) $m[1]);
                }
            });

        return (string) ($ultimo + 1);
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

    public function getUnidadesProperty()
    {
        return UnidadCatalogo::where('active', true)
            ->where(fn ($q) => $q->where('uso', 'REQ')->orWhere('uso', 'AMBOS'))
            ->orderBy('abreviatura')
            ->pluck('abreviatura');
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
            'prioridad' => 'Normal',
            'fecha_requerida' => null,
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
            'items.*.prioridad' => 'nullable|string|in:Normal,Prioritario,Urgente',
            'items.*.fecha_requerida' => 'nullable|date',
            'archivoExterno' => 'nullable|file|mimes:pdf,xls,xlsx,xlsm|max:10240',
            'imagenes' => 'array',
            'imagenes.*' => 'array|max:5',
            'imagenes.*.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'numeroSecuencial.regex' => 'El número de requerimiento solo debe contener dígitos.',
        ]);

        abort_unless(auth()->user()->hasAccessToObra($this->obra_id), 403);

        $user = auth()->user();
        $esRolDeOficina = $user->hasAnyRole(TramiteRoles::OFFICE_REQUIREMENT_ROLES)
            && ! $user->hasAnyRole(TramiteRoles::TRAMITE_CREATOR_ROLES);

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
            $this->addError('numeroSecuencial', "El requerimiento N.° {$numero} ya existe. Modifique el correlativo e inténtelo nuevamente.");

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
                    'prioridad' => $item['prioridad'] ?? 'Normal',
                    'fecha_requerida' => $item['fecha_requerida'] ?: null,
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
<div class="space-y-3">
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
            <div class="border-b border-zinc-100 p-4 dark:border-zinc-700">
                <div class="mb-3 text-sm font-semibold text-[#142f44] dark:text-white">Datos generales</div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <flux:label>N° de requerimiento</flux:label>
                        <div class="mt-1 flex items-stretch overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                            <input
                                type="number"
                                min="1"
                                step="1"
                                wire:model="numeroSecuencial"
                                class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm font-semibold text-zinc-600 focus:ring-0 dark:text-zinc-300"
                            />
                            <span class="flex items-center px-1 text-zinc-300">-</span>
                            <span class="flex items-center bg-zinc-100 px-3 text-sm font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ $anioActual }}
                            </span>
                        </div>
                        <flux:text class="mt-1 text-xs text-zinc-400">Se sugiere automáticamente en base al último requerimiento registrado en esta obra. Puede modificarlo si ya existe.</flux:text>
                        @error('numeroSecuencial') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
                    </div>

                    <flux:input type="date" label="Fecha" wire:model="fecha" required />
                </div>
            </div>

            <!-- Tipo de requerimiento -->
            <div class="border-b border-zinc-100 p-4 dark:border-zinc-700">
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

            <!-- Relación de materiales -->
            @if ($tipoEjecucion || $tipoSeguridad || $tipoOficina)
                <div class="border-b border-zinc-100 p-4 dark:border-zinc-700">
                    <div class="mb-3">
                        <div class="text-sm font-semibold text-[#142f44] dark:text-white">Relación de materiales</div>
                        <div class="text-xs text-zinc-500">
                            Ingrese los materiales por sección. Use <span class="font-medium text-zinc-700 dark:text-zinc-300">Detalles</span> para prioridad, fecha requerida e imágenes de referencia.
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-left text-sm" x-data="reqMateriales()">
                            <thead>
                                <tr class="bg-[#142f44] text-xs text-white uppercase">
                                    <th class="px-3 py-2 text-center font-medium">N°</th>
                                    <th class="px-3 py-2 font-medium">Descripción</th>
                                    <th class="px-3 py-2 font-medium">Cant.</th>
                                    <th class="px-3 py-2 font-medium">Und</th>
                                    <th class="px-3 py-2 font-medium">Stock</th>
                                    <th class="px-3 py-2 text-center font-medium">A comprar</th>
                                    <th class="px-3 py-2 font-medium">Justificación</th>
                                    <th class="px-3 py-2 font-medium">Detalles</th>
                                    <th class="px-3 py-2 font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($this->tipos as $tipoInfo)
                                    @continue(! $tipoInfo['activo'])
                                    @php($contador = 0)

                                    <tr class="bg-blue-50/70 dark:bg-blue-950/20">
                                        <td colspan="9" class="px-3 py-1.5 text-xs font-semibold uppercase text-[#142f44] dark:text-white">
                                            <flux:icon :icon="$tipoInfo['icon']" class="mr-1 -mt-0.5 inline size-3.5" />{{ $tipoInfo['titulo'] }}
                                        </td>
                                    </tr>

                                    @foreach ($items as $index => $item)
                                        @continue($item['seccion'] !== $tipoInfo['seccion'])
                                        @php($contador++)

                                        <tr wire:key="req-item-{{ $index }}">
                                            <td class="px-3 py-2 text-center align-middle text-zinc-500">{{ str_pad($contador, 2, '0', STR_PAD_LEFT) }}</td>
                                            <td class="px-3 py-2 align-top min-w-48">
                                                <flux:textarea rows="1" wire:model="items.{{ $index }}.descripcion" placeholder="Descripción del material..." />
                                            </td>
                                            <td class="px-3 py-2 align-top w-24">
                                                <flux:input type="number" step="0.01" min="0" wire:model.live="items.{{ $index }}.cantidad" placeholder="Cant." />
                                            </td>
                                            <td class="px-3 py-2 align-top w-28">
                                                <flux:select wire:model="items.{{ $index }}.unidad" placeholder="Seleccione">
                                                    @foreach ($this->unidades as $unidad)
                                                        <flux:select.option value="{{ $unidad }}">{{ $unidad }}</flux:select.option>
                                                    @endforeach
                                                </flux:select>
                                            </td>
                                            <td class="px-3 py-2 align-top w-24">
                                                <flux:input type="number" step="0.01" min="0" wire:model.live="items.{{ $index }}.stock" placeholder="Stock" />
                                            </td>
                                            <td class="px-3 py-2 w-24 text-center align-middle font-semibold whitespace-nowrap text-[#142f44] dark:text-white">
                                                {{ $this->comprar[$index] ?? 0 }}
                                            </td>
                                            <td class="px-3 py-2 align-top min-w-40">
                                                <flux:textarea rows="1" wire:model="items.{{ $index }}.justificacion" placeholder="Justificación..." />
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <flux:button type="button" size="sm" variant="ghost" icon="adjustments-horizontal" x-on:click="detallesAbiertos[{{ $index }}] = ! detallesAbiertos[{{ $index }}]">Detalles</flux:button>
                                            </td>
                                            <td class="px-3 py-2 align-top">
                                                <div class="flex items-center gap-1">
                                                    <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addItemTo('{{ $tipoInfo['seccion'] }}')" />
                                                    <flux:button type="button" size="sm" variant="ghost" icon="x-mark" class="text-red-500!" wire:click="removeItem({{ $index }})" />
                                                </div>
                                            </td>
                                        </tr>

                                        <tr x-show="detallesAbiertos[{{ $index }}]" x-cloak wire:key="req-item-detalles-{{ $index }}">
                                            <td colspan="9" class="bg-blue-50/60 px-3 py-3 dark:bg-blue-950/20">
                                                <div class="rounded-lg border border-blue-100 bg-white p-3 dark:border-blue-900 dark:bg-zinc-800">
                                                    <div class="mb-3 flex items-start justify-between gap-3">
                                                        <div>
                                                            <div class="text-xs font-semibold text-[#142f44] dark:text-white">Información interna y referencias</div>
                                                            <div class="text-xs text-zinc-500">Complete solo lo necesario. Prioridad y fecha no se imprimen en el PDF.</div>
                                                        </div>
                                                        <flux:button type="button" size="sm" variant="ghost" icon="x-mark" x-on:click="detallesAbiertos[{{ $index }}] = false" />
                                                    </div>

                                                    <div class="grid gap-3 sm:grid-cols-3">
                                                        <div>
                                                            <flux:select label="Prioridad" wire:model="items.{{ $index }}.prioridad">
                                                                <flux:select.option value="Normal">Normal</flux:select.option>
                                                                <flux:select.option value="Prioritario">Prioritario</flux:select.option>
                                                                <flux:select.option value="Urgente">Urgente</flux:select.option>
                                                            </flux:select>
                                                            <div class="mt-1 text-xs text-zinc-400">Normal por defecto. Cámbiela solo si corresponde.</div>
                                                        </div>

                                                        <div>
                                                            <flux:input type="date" label="Fecha requerida (opcional)" wire:model="items.{{ $index }}.fecha_requerida" />
                                                            <div class="mt-1 text-xs text-zinc-400">Si queda vacía, no se guarda ninguna fecha.</div>
                                                        </div>

                                                        <div>
                                                            <flux:label>Imágenes de referencia (opcional)</flux:label>
                                                            <label class="mt-1 flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-sm transition hover:border-[#142f44]/40 dark:border-zinc-600">
                                                                <span class="flex min-w-0 items-center gap-2 font-medium text-[#142f44] dark:text-white">
                                                                    <flux:icon.photo class="size-4 shrink-0 text-zinc-400" />
                                                                    <span class="truncate">Adjuntar imágenes</span>
                                                                </span>
                                                                <span class="shrink-0 text-xs text-zinc-400">JPG, PNG o WEBP · Máx. 5</span>
                                                                <input type="file" multiple accept=".jpg,.jpeg,.png,.webp" class="hidden" x-on:change="agregarImagenes({{ $index }}, $event.target.files); $event.target.value = ''" />
                                                            </label>

                                                            <div class="mt-1 text-xs text-zinc-400" x-text="(imagenesSeleccionadas[{{ $index }}] || []).length ? (imagenesSeleccionadas[{{ $index }}].length + (imagenesSeleccionadas[{{ $index }}].length === 1 ? ' imagen' : ' imágenes')) : 'Sin imágenes'"></div>

                                                            <div class="mt-2 flex flex-wrap gap-2" x-show="(imagenesSeleccionadas[{{ $index }}] || []).length">
                                                                <template x-for="(imagen, i) in (imagenesSeleccionadas[{{ $index }}] || [])" :key="imagen.url">
                                                                    <div class="group relative size-14 shrink-0 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-600">
                                                                        <img :src="imagen.url" class="size-full object-cover" />
                                                                        <button type="button" class="absolute end-0.5 top-0.5 flex size-4 items-center justify-center rounded-full bg-white text-red-500 shadow" x-on:click="quitarImagen({{ $index }}, i)" title="Quitar imagen" aria-label="Quitar imagen">
                                                                            <flux:icon.x-mark class="size-3" />
                                                                        </button>
                                                                    </div>
                                                                </template>
                                                            </div>

                                                            <div class="mt-1 text-xs text-zinc-400">JPG, PNG o WEBP. Puede agregar imágenes en varias selecciones hasta completar 5.</div>
                                                            @error("imagenes.{$index}.*") <flux:text class="mt-1 text-xs text-red-500">{{ $message }}</flux:text> @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @error('items') <flux:text class="mt-2 block text-red-500">{{ $message }}</flux:text> @enderror

                    <flux:modal name="limite-imagenes" class="max-w-sm">
                        <div class="flex flex-col items-center gap-3 text-center">
                            <div class="flex size-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                                <flux:icon.exclamation-triangle class="size-6 text-amber-500" />
                            </div>
                            <div>
                                <flux:heading size="lg" class="text-[#142f44] dark:text-white">Máximo de imágenes</flux:heading>
                                <flux:text class="mt-1 text-zinc-500">Este ítem admite como máximo 5 imágenes de referencia. Quite una imagen si desea agregar otra.</flux:text>
                            </div>
                            <flux:modal.close>
                                <flux:button variant="primary" class="mt-1 !bg-[#142f44] hover:!bg-[#0d2032]">Entendido</flux:button>
                            </flux:modal.close>
                        </div>
                    </flux:modal>
                </div>
            @endif

            <!-- Archivo externo -->
            <div class="border-b border-zinc-100 p-4 dark:border-zinc-700">
                <div class="mb-3 flex items-center gap-1.5 text-sm font-semibold text-[#142f44] dark:text-white">
                    Archivo externo del requerimiento
                    <span class="text-xs font-normal text-zinc-400">(opcional)</span>
                </div>

                <flux:label>Adjuntar copia en PDF o Excel</flux:label>
                <label class="mt-1 flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-dashed border-zinc-300 px-3 py-3 text-sm transition hover:border-[#142f44]/40 dark:border-zinc-600">
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
            <div class="p-4">
                <div class="mb-3 text-sm font-semibold text-[#142f44] dark:text-white">Identificación del trámite</div>

                <div class="grid gap-3 sm:grid-cols-3">
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

        <div class="mt-3 flex justify-end gap-3">
            <flux:button variant="ghost" :href="route('tramites.create')" wire:navigate>Cancelar</flux:button>
            <flux:button type="submit" variant="primary" icon="document-check" class="!bg-[#142f44] hover:!bg-[#0d2032]">
                Guardar requerimiento
            </flux:button>
        </div>
    </form>

    <script>
        (function () {
            function registrarReqMateriales() {
                if (!window.Alpine) return;

                Alpine.data('reqMateriales', () => ({
                    detallesAbiertos: {},
                    imagenesSeleccionadas: {},

                    agregarImagenes(index, fileList) {
                        const existentes = this.imagenesSeleccionadas[index] || [];
                        const nuevos = Array.from(fileList);
                        const disponibles = Math.max(5 - existentes.length, 0);
                        const aceptados = nuevos.slice(0, disponibles);

                        this.imagenesSeleccionadas[index] = existentes.concat(
                            aceptados.map((file) => ({ file, url: URL.createObjectURL(file) }))
                        );

                        this.$wire.uploadMultiple(
                            'imagenes.' + index,
                            this.imagenesSeleccionadas[index].map((imagen) => imagen.file)
                        );

                        if (nuevos.length > disponibles) {
                            this.$flux.modal('limite-imagenes').show();
                        }
                    },

                    quitarImagen(index, i) {
                        const actuales = (this.imagenesSeleccionadas[index] || []).slice();
                        const [removida] = actuales.splice(i, 1);

                        if (removida) {
                            URL.revokeObjectURL(removida.url);
                        }

                        this.imagenesSeleccionadas[index] = actuales;

                        this.$wire.uploadMultiple(
                            'imagenes.' + index,
                            actuales.map((imagen) => imagen.file)
                        );
                    },
                }));
            }

            document.addEventListener('alpine:init', registrarReqMateriales);
            document.addEventListener('livewire:navigated', registrarReqMateriales);
            registrarReqMateriales();
        })();
    </script>
</div>
