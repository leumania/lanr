<div class="space-y-3">
    <div class="flex items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" class="text-[#142f44]">Editar {{ $tramite->tracking }}</flux:heading>
            <flux:text class="text-zinc-500">Solo puedes editarlo mientras esté pendiente de tu revisión.</flux:text>
        </div>
        <flux:button href="{{ route('tramites.show', $tramite) }}" variant="ghost" wire:navigate>Cancelar</flux:button>
    </div>

    @if ($errors->any())<flux:callout variant="danger" heading="{{ $errors->first() }}" />@endif

    <form wire:submit="save" class="flex flex-col gap-3">
        <flux:card class="p-3">
            <flux:heading size="lg" class="mb-3 text-[#142f44]">Datos generales</flux:heading>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <flux:label>N°</flux:label>
                    <div class="mt-1 flex items-stretch overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                        @if ($tramite->tipo === 'SP')
                            <span class="flex items-center bg-zinc-100 px-3 text-sm font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $anioActual }}</span>
                            <span class="flex items-center px-1 text-zinc-300">-</span>
                            <input type="text" wire:model="numeroSecuencial" readonly tabindex="-1" class="min-w-0 flex-1 cursor-not-allowed border-0 bg-transparent px-3 py-2 text-sm font-semibold text-zinc-600 focus:ring-0 dark:text-zinc-300" />
                        @else
                            <input type="text" wire:model="numeroSecuencial" readonly tabindex="-1" class="min-w-0 flex-1 cursor-not-allowed border-0 bg-transparent px-3 py-2 text-sm font-semibold text-zinc-600 focus:ring-0 dark:text-zinc-300" />
                            <span class="flex items-center px-1 text-zinc-300">-</span>
                            <span class="flex items-center bg-zinc-100 px-3 text-sm font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $anioActual }}</span>
                        @endif
                    </div>
                    <flux:text class="mt-1 text-xs text-zinc-400">El número se asignó al registrar el trámite y no puede modificarse.</flux:text>
                </div>

                <flux:input type="date" label="Fecha" wire:model="fecha" />

                @if ($tramite->tipo === 'SP')
                    <flux:select label="Tipo de solicitud" wire:model="subtipo">
                        <flux:select.option value="Proveedor persona natural">Proveedor persona natural</flux:select.option>
                        <flux:select.option value="Proveedor persona jurídica">Proveedor persona jurídica</flux:select.option>
                        <flux:select.option value="Cuarta categoría / Recibo por Honorarios">Cuarta categoría / Recibo por Honorarios</flux:select.option>
                        <flux:select.option value="Planillas">Planillas</flux:select.option>
                    </flux:select>
                    <flux:input label="Beneficiario" wire:model="beneficiario" />
                    <flux:input label="DNI / RUC" wire:model="dniRuc" />
                    <flux:input label="Responsable" wire:model="responsable" />
                    <flux:input label="N° celular" wire:model="celular" />
                @endif
            </div>
        </flux:card>

        @if ($tramite->tipo === 'SP')
            <flux:card class="p-3">
                <flux:heading size="lg" class="mb-3 text-[#142f44]">Modalidad de pago</flux:heading>
                <div class="flex flex-wrap gap-3">
                    @foreach (self::MODALIDADES as $modalidad)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-zinc-200 px-3.5 py-2 text-sm dark:border-zinc-700">
                            <input type="checkbox" wire:click="toggleModalidad('{{ $modalidad }}')" @checked(in_array($modalidad, $modalidades, true)) class="rounded border-zinc-300 text-[#142f44] focus:ring-[#142f44]" />
                            {{ $modalidad }}
                        </label>
                    @endforeach
                </div>
                @if (in_array('Otro', $modalidades, true))
                    <flux:input class="mt-3" label="Otra modalidad" wire:model="modalidadOtroTexto" />
                @endif
            </flux:card>

            <flux:card class="p-3">
                <flux:heading size="lg" class="mb-3 text-[#142f44]">Banco y cuenta / CCI</flux:heading>
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
                                <flux:input label="Cuenta / CCI" wire:model="cuentas.{{ $index }}.cuenta_cci" />
                            </div>
                            <flux:button class="mt-4" variant="ghost" size="sm" icon="trash" wire:click="removeCuenta({{ $index }})" />
                        </div>
                    @endforeach
                </div>
                <flux:button class="mt-3" variant="ghost" size="sm" icon="plus" wire:click="addCuenta">Agregar banco / cuenta</flux:button>
            </flux:card>
        @endif

        <flux:card class="p-3">
            <div class="mb-3">
                <flux:heading size="lg" class="text-[#142f44]">Ítems</flux:heading>
                @if ($tramite->tipo === 'REQ')
                    <div class="text-xs text-zinc-500">Use <span class="font-medium text-zinc-700 dark:text-zinc-300">Detalles</span> para prioridad, fecha requerida e imágenes de referencia.</div>
                @endif
            </div>

            @if ($tramite->tipo === 'REQ')
                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-left text-sm" x-data="editReqItems()">
                        <thead>
                            <tr class="bg-[#142f44] text-xs text-white uppercase">
                                <th class="px-3 py-2 text-center font-medium">N°</th>
                                <th class="px-3 py-2 font-medium">Sección</th>
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
                            @foreach ($items as $index => $item)
                                <tr wire:key="edit-item-{{ $index }}">
                                    <td class="px-3 py-2 text-center align-middle text-zinc-500">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                    <td class="px-3 py-2 align-top">
                                        <flux:select wire:model="items.{{ $index }}.seccion">
                                            <flux:select.option value="Ejecución de Obra">Ejecución de obra</flux:select.option>
                                            <flux:select.option value="Ing. de Seguridad">Seguridad en obra</flux:select.option>
                                            <flux:select.option value="Útiles de Oficina">Útiles de oficina</flux:select.option>
                                        </flux:select>
                                    </td>
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
                                        @if (count($items) > 1)
                                            <flux:button type="button" size="sm" variant="ghost" icon="x-mark" class="text-red-500!" wire:click="removeItem({{ $index }})" />
                                        @endif
                                    </td>
                                </tr>

                                <tr x-show="detallesAbiertos[{{ $index }}]" x-cloak wire:key="edit-item-detalles-{{ $index }}">
                                    <td colspan="10" class="bg-blue-50/60 px-3 py-3 dark:bg-blue-950/20">
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
                                                        <input type="file" multiple accept=".jpg,.jpeg,.png,.webp" class="hidden" x-on:change="agregarImagenes({{ $index }}, {{ count($imagenesExistentes[$index] ?? []) }}, $event.target.files); $event.target.value = ''" />
                                                    </label>

                                                    <div class="mt-2 flex flex-wrap gap-2">
                                                        @foreach ($imagenesExistentes[$index] ?? [] as $imagenExistente)
                                                            <div class="group relative size-14 shrink-0 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-600">
                                                                <img src="{{ $imagenExistente['url'] }}" class="size-full object-cover" />
                                                                <button type="button" class="absolute end-0.5 top-0.5 flex size-4 items-center justify-center rounded-full bg-white text-red-500 shadow" wire:click="eliminarImagenExistente({{ $index }}, {{ $imagenExistente['id'] }})" wire:confirm="¿Quitar esta imagen?" title="Quitar imagen" aria-label="Quitar imagen">
                                                                    <flux:icon.x-mark class="size-3" />
                                                                </button>
                                                            </div>
                                                        @endforeach

                                                        <template x-for="(imagen, i) in (imagenesNuevas[{{ $index }}] || [])" :key="imagen.url">
                                                            <div class="group relative size-14 shrink-0 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-600">
                                                                <img :src="imagen.url" class="size-full object-cover" />
                                                                <button type="button" class="absolute end-0.5 top-0.5 flex size-4 items-center justify-center rounded-full bg-white text-red-500 shadow" x-on:click="quitarImagenNueva({{ $index }}, i)" title="Quitar imagen" aria-label="Quitar imagen">
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
                        </tbody>
                    </table>
                </div>

                <flux:modal name="limite-imagenes-edicion" class="max-w-sm">
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
            @else
                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-left text-sm" x-data="{ detallesAbiertos: {} }">
                        <thead>
                            <tr class="bg-[#142f44] text-xs text-white uppercase">
                                <th class="px-3 py-2 text-center font-medium">N°</th>
                                <th class="px-3 py-2 font-medium">Concepto</th>
                                <th class="px-3 py-2 font-medium">Unidad</th>
                                <th class="px-3 py-2 font-medium">Cantidad</th>
                                <th class="px-3 py-2 font-medium">Costo unit.</th>
                                <th class="px-3 py-2 text-center font-medium">Monto</th>
                                <th class="px-3 py-2 font-medium">Detalles</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($items as $index => $item)
                                <tr x-data="{ detalles: false }" wire:key="edit-sp-item-{{ $index }}">
                                    <td class="px-3 py-2 text-center align-middle text-zinc-500">{{ $index + 1 }}</td>
                                    <td class="px-3 py-2 align-top"><flux:input wire:model="items.{{ $index }}.concepto" placeholder="Concepto" /></td>
                                    <td class="px-3 py-2 align-top">
                                        <flux:select wire:model="items.{{ $index }}.unidad" placeholder="UND.">
                                            @foreach ($this->unidades as $unidad)
                                                <flux:select.option value="{{ $unidad }}">{{ $unidad }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </td>
                                    <td class="px-3 py-2 align-top"><flux:input type="number" step="0.01" wire:model.live="items.{{ $index }}.cantidad" /></td>
                                    <td class="px-3 py-2 align-top"><flux:input type="number" step="0.01" wire:model.live="items.{{ $index }}.costo" /></td>
                                    <td class="px-3 py-2 text-center align-middle font-semibold whitespace-nowrap text-[#142f44] dark:text-white">
                                        {{ $this->monedaSimbolo }} {{ number_format($this->montos[$index] ?? 0, 2) }}
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <div class="flex items-center gap-1">
                                            <flux:button type="button" size="sm" variant="ghost" x-on:click="detalles = ! detalles">Detalles</flux:button>
                                            @if (count($items) > 1)
                                                <flux:button type="button" variant="ghost" size="sm" icon="trash" wire:click="removeItem({{ $index }})" />
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
            @endif

            <flux:button type="button" class="mt-3" variant="ghost" size="sm" icon="plus" wire:click="addItem">
                Agregar ítem
            </flux:button>

            @error('items') <flux:text class="mt-2 block text-red-500">{{ $message }}</flux:text> @enderror
        </flux:card>

        <flux:card class="p-3">
            <flux:textarea label="Observaciones" wire:model="observaciones" rows="2" />
        </flux:card>

        <flux:card class="p-3">
            <flux:heading size="lg" class="mb-3 text-[#142f44]">Documentos</flux:heading>

            @if ($tramite->attachments->isNotEmpty())
                <div class="mb-3 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($tramite->attachments as $adjunto)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span class="truncate">{{ $adjunto->nombre_original }}</span>
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="eliminarAdjunto({{ $adjunto->id }})" wire:confirm="¿Eliminar este documento?" />
                        </div>
                    @endforeach
                </div>
            @endif

            <flux:label>Agregar documentos</flux:label>
            <input type="file" wire:model="adjuntos" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full cursor-pointer rounded-lg border border-zinc-200 bg-white py-1.5 ps-1 text-sm text-zinc-600 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-200" />
            @error('adjuntos.*') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" class="!bg-[#142f44] hover:!bg-[#0d2032]">Guardar cambios</flux:button>
        </div>
    </form>

    @if ($tramite->tipo === 'REQ')
        <script>
            (function () {
                function registrarEditReqItems() {
                    if (!window.Alpine) return;

                    Alpine.data('editReqItems', () => ({
                        detallesAbiertos: {},
                        imagenesNuevas: {},

                        agregarImagenes(index, existentes, fileList) {
                            const actuales = this.imagenesNuevas[index] || [];
                            const nuevos = Array.from(fileList);
                            const disponibles = Math.max(5 - existentes - actuales.length, 0);
                            const aceptados = nuevos.slice(0, disponibles);

                            this.imagenesNuevas[index] = actuales.concat(
                                aceptados.map((file) => ({ file, url: URL.createObjectURL(file) }))
                            );

                            this.$wire.uploadMultiple(
                                'imagenes.' + index,
                                this.imagenesNuevas[index].map((imagen) => imagen.file)
                            );

                            if (nuevos.length > disponibles) {
                                this.$flux.modal('limite-imagenes-edicion').show();
                            }
                        },

                        quitarImagenNueva(index, i) {
                            const actuales = (this.imagenesNuevas[index] || []).slice();
                            const [removida] = actuales.splice(i, 1);

                            if (removida) {
                                URL.revokeObjectURL(removida.url);
                            }

                            this.imagenesNuevas[index] = actuales;

                            this.$wire.uploadMultiple(
                                'imagenes.' + index,
                                actuales.map((imagen) => imagen.file)
                            );
                        },
                    }));
                }

                document.addEventListener('alpine:init', registrarEditReqItems);
                document.addEventListener('livewire:navigated', registrarEditReqItems);
                registrarEditReqItems();
            })();
        </script>
    @endif
</div>
