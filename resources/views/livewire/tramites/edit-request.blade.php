<div class="space-y-4">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="text-[#142f44]">Editar {{ $tramite->tracking }}</flux:heading>
            <flux:text class="text-zinc-500">Solo puedes editarlo mientras esté pendiente de tu revisión.</flux:text>
        </div>
        <flux:button href="{{ route('tramites.show', $tramite) }}" variant="ghost" wire:navigate>Cancelar</flux:button>
    </div>

    @if ($errors->any())<flux:callout variant="danger" heading="{{ $errors->first() }}" />@endif

    <datalist id="unidades-catalogo">
        @foreach ($this->unidades as $unidad)
            <option value="{{ $unidad }}"></option>
        @endforeach
    </datalist>

    <form wire:submit="save" class="flex flex-col gap-4">
        <flux:card class="p-4">
            <flux:heading size="lg" class="mb-3 text-[#142f44]">Datos generales</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:label>N°</flux:label>
                    <div class="mt-1 flex items-stretch overflow-hidden rounded-lg border border-zinc-200 focus-within:border-[#142f44] dark:border-zinc-700">
                        @if ($tramite->tipo === 'SP')
                            <span class="flex items-center bg-zinc-50 px-3 text-sm font-semibold text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">{{ $anioActual }}</span>
                            <span class="flex items-center px-1 text-zinc-300">-</span>
                            <input type="text" inputmode="numeric" wire:model="numeroSecuencial" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm focus:ring-0" />
                        @else
                            <input type="text" inputmode="numeric" wire:model="numeroSecuencial" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm focus:ring-0" />
                            <span class="flex items-center px-1 text-zinc-300">-</span>
                            <span class="flex items-center bg-zinc-50 px-3 text-sm font-semibold text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">{{ $anioActual }}</span>
                        @endif
                    </div>
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
            <flux:card class="p-4">
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

            <flux:card class="p-4">
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
                            <flux:button class="mt-6" variant="ghost" size="sm" icon="trash" wire:click="removeCuenta({{ $index }})" />
                        </div>
                    @endforeach
                </div>
                <flux:button class="mt-3" variant="ghost" size="sm" icon="plus" wire:click="addCuenta">Agregar banco / cuenta</flux:button>
            </flux:card>
        @endif

        <flux:card class="p-4">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg" class="text-[#142f44]">Ítems</flux:heading>
                <flux:button type="button" size="sm" icon="plus" wire:click="addItem">Agregar</flux:button>
            </div>

            <div class="space-y-3">
                @foreach ($items as $index => $item)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="mb-3 flex items-center justify-between">
                            <flux:badge>Ítem {{ $index + 1 }}</flux:badge>
                            @if (count($items) > 1)
                                <flux:button type="button" variant="ghost" size="sm" icon="trash" wire:click="removeItem({{ $index }})" />
                            @endif
                        </div>

                        @if ($tramite->tipo === 'REQ')
                            <div class="grid gap-3 sm:grid-cols-2">
                                <flux:select label="Sección" wire:model="items.{{ $index }}.seccion">
                                    <flux:select.option value="Ejecución de Obra">Ejecución de obra</flux:select.option>
                                    <flux:select.option value="Ing. de Seguridad">Seguridad en obra</flux:select.option>
                                    <flux:select.option value="Útiles de Oficina">Útiles de oficina</flux:select.option>
                                </flux:select>
                                <div>
                                    <flux:label>Unidad</flux:label>
                                    <input list="unidades-catalogo" wire:model="items.{{ $index }}.unidad" class="mt-1 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-white/10" />
                                </div>
                                <div class="sm:col-span-2">
                                    <flux:textarea label="Descripción" wire:model="items.{{ $index }}.descripcion" rows="2" />
                                </div>
                                <flux:input type="number" step="0.01" label="Cantidad" wire:model="items.{{ $index }}.cantidad" />
                                <flux:input type="number" step="0.01" label="Stock" wire:model="items.{{ $index }}.stock" />
                                <flux:select label="Prioridad" wire:model="items.{{ $index }}.prioridad">
                                    <flux:select.option value="Normal">Normal</flux:select.option>
                                    <flux:select.option value="Prioritario">Prioritario</flux:select.option>
                                    <flux:select.option value="Urgente">Urgente</flux:select.option>
                                </flux:select>
                                <flux:input type="date" label="Fecha requerida (opcional)" wire:model="items.{{ $index }}.fecha_requerida" />
                                <div class="sm:col-span-2">
                                    <flux:textarea label="Justificación" wire:model="items.{{ $index }}.justificacion" rows="2" />
                                </div>
                            </div>
                        @else
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="sm:col-span-3">
                                    <flux:input label="Concepto" wire:model="items.{{ $index }}.concepto" />
                                </div>
                                <div>
                                    <flux:label>Unidad</flux:label>
                                    <input list="unidades-catalogo" wire:model="items.{{ $index }}.unidad" class="mt-1 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-white/10" />
                                </div>
                                <flux:input type="number" step="0.01" label="Cantidad" wire:model="items.{{ $index }}.cantidad" />
                                <flux:input type="number" step="0.01" label="Costo unitario" wire:model="items.{{ $index }}.costo" />
                                <div class="sm:col-span-3">
                                    <flux:input label="N° despacho (opcional)" wire:model="items.{{ $index }}.nro_despacho" />
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card class="p-4">
            <flux:textarea label="Observaciones" wire:model="observaciones" rows="2" />
        </flux:card>

        <flux:card class="p-4">
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
            <input type="file" wire:model="adjuntos" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-sm" />
            @error('adjuntos.*') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" class="!bg-[#142f44] hover:!bg-[#0d2032]">Guardar cambios</flux:button>
        </div>
    </form>
</div>
