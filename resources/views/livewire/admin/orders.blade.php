<div class="space-y-3">
    <div>
        <flux:heading size="xl">Órdenes de compra y servicio</flux:heading>
        <flux:text class="mt-1">Registra órdenes para la obra activa y gestiona sus vistos buenos.</flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success" heading="{{ session('status') }}" />
    @endif
    @if ($errors->any())
        <flux:callout variant="danger" heading="{{ $errors->first() }}" />
    @endif

    <flux:card>
        <form wire:submit="save" class="grid gap-3 sm:grid-cols-2">
            <flux:select label="Tipo" wire:model.live="tipoOrden">
                <flux:select.option value="Orden de compra">Orden de compra</flux:select.option>
                <flux:select.option value="Orden de servicio">Orden de servicio</flux:select.option>
            </flux:select>

            <div>
                <flux:label>Número</flux:label>
                <input type="text" wire:model="numero" readonly tabindex="-1" class="mt-1 block w-full cursor-not-allowed rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm font-semibold text-zinc-600 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300" />
                <flux:text class="mt-1 text-xs text-zinc-400">Se asigna automáticamente según el último número registrado para este tipo.</flux:text>
                @error('numero') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
            </div>

            <flux:input type="date" label="Fecha" wire:model="fecha" />
            <flux:input label="Proveedor" wire:model="proveedor" />
            <flux:input label="RUC / DNI" wire:model="documentoProveedor" />

            <flux:select label="Moneda" wire:model="moneda">
                @foreach ($this->monedas as $monedaOpcion)
                    <flux:select.option :value="$monedaOpcion->codigo">{{ $monedaOpcion->codigo }} ({{ $monedaOpcion->nombre }})</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="number" step="0.01" label="Total" wire:model="total" />

            <div class="sm:col-span-2">
                <flux:textarea label="Descripción" wire:model="descripcion" />
            </div>

            <div class="sm:col-span-2 space-y-3">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">Detalle</flux:heading>
                    <flux:button type="button" size="sm" icon="plus" wire:click="addItem">Agregar ítem</flux:button>
                </div>

                @foreach ($items as $index => $item)
                    <div class="grid gap-2 rounded border border-zinc-200 p-3 sm:grid-cols-4 dark:border-zinc-700">
                        <flux:input label="Descripción" wire:model="items.{{ $index }}.descripcion" />

                        <flux:select label="Unidad" wire:model="items.{{ $index }}.unidad" placeholder="Seleccione">
                            @foreach ($this->unidades as $unidadOpcion)
                                <flux:select.option :value="$unidadOpcion">{{ $unidadOpcion }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input type="number" step="0.01" label="Cantidad" wire:model="items.{{ $index }}.cantidad" />

                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <flux:input type="number" step="0.01" label="Precio unitario" wire:model="items.{{ $index }}.precio_unitario" />
                            </div>
                            @if (count($items) > 1)
                                <flux:button type="button" variant="ghost" size="sm" icon="trash" wire:click="removeItem({{ $index }})" />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="sm:col-span-2">
                <flux:label>Documentos <span class="text-xs font-normal text-zinc-400">(opcional)</span></flux:label>
                <input type="file" wire:model="archivos" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full cursor-pointer rounded-lg border border-zinc-200 bg-white py-1.5 ps-1 text-sm text-zinc-600 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-200" />
                <flux:text class="mt-1 text-xs text-zinc-400">Máximo 5 archivos en formato PDF o imagen (JPG, PNG o WEBP).</flux:text>
                @error('archivos') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
                @error('archivos.*') <flux:text class="mt-1 text-sm text-red-500">{{ $message }}</flux:text> @enderror
            </div>

            <div class="sm:col-span-2 flex justify-end">
                <flux:button type="submit" variant="primary">Registrar orden</flux:button>
            </div>
        </form>
    </flux:card>

    <flux:card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="py-3 pe-4">Número</th>
                        <th class="py-3 pe-4">Proveedor</th>
                        <th class="py-3 pe-4">Total</th>
                        <th class="py-3 pe-4">Estado</th>
                        <th class="py-3 pe-4">Documentos</th>
                        <th class="py-3">Aprobaciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($ordenes as $orden)
                        <tr>
                            <td class="py-3 pe-4">{{ $orden->numero }}</td>
                            <td class="py-3 pe-4">{{ $orden->proveedor }}</td>
                            <td class="py-3 pe-4">{{ $orden->moneda }} {{ number_format($orden->total, 2) }}</td>
                            <td class="py-3 pe-4">{{ $orden->estado }}</td>
                            <td class="py-3 pe-4">
                                @foreach ($orden->adjuntos as $adjunto)
                                    <a class="block underline" href="{{ route('orders.files.download', $adjunto) }}">{{ $adjunto->nombre_original }}</a>
                                @endforeach
                            </td>
                            <td class="flex flex-wrap gap-2 py-3">
                                @if (auth()->user()->hasRole('Gerencia de Obra') && ! $orden->vobo_gerencia_obra)
                                    <flux:button size="sm" wire:click="approve({{ $orden->id }})">V°B° Obra</flux:button>
                                @endif
                                @if (auth()->user()->hasRole('Administración') && ! $orden->vobo_administracion)
                                    <flux:button size="sm" wire:click="approve({{ $orden->id }})">V°B° Administración</flux:button>
                                @endif
                                @if (auth()->user()->hasRole('Gerencia General') && ! $orden->vobo_gerencia_general)
                                    <flux:button size="sm" wire:click="approve({{ $orden->id }})">V°B° Gerencia General</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-center text-zinc-500">No hay órdenes registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>
</div>
