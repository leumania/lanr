<div class="space-y-3">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><flux:text>{{ $tramite->tracking }}</flux:text><flux:heading size="xl">Gestión de cotizaciones</flux:heading><flux:text class="mt-1">Estado: {{ $tramite->estado }}</flux:text></div>
        <flux:button href="{{ route('tramites.show', $tramite) }}" variant="ghost" wire:navigate>Volver al trámite</flux:button>
    </div>
    @if (session('status'))<flux:callout variant="success" heading="{{ session('status') }}" />@endif
    @if ($errors->any())<flux:callout variant="danger" heading="{{ $errors->first() }}" />@endif
    @if ($this->avance->isNotEmpty())
        <flux:card class="p-3">
            <flux:heading size="lg" class="text-[#142f44]">Avance de compra por ítem</flux:heading>
            <flux:text class="mt-1 text-zinc-500">Cantidad requerida, autorizada y pendiente por cada ítem a comprar.</flux:text>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-100 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                            <th class="pb-2 font-medium">Ítem</th>
                            <th class="pb-2 font-medium">Requerido</th>
                            <th class="pb-2 font-medium">Autorizado</th>
                            <th class="pb-2 font-medium">Pendiente</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->avance as $fila)
                            <tr>
                                <td class="py-2">{{ $fila['descripcion'] }}</td>
                                <td class="py-2">{{ $fila['requerido'] }}</td>
                                <td class="py-2">{{ $fila['autorizado'] }}</td>
                                <td class="py-2 font-semibold {{ $fila['pendiente'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">{{ $fila['pendiente'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif
    @if (auth()->user()->hasAnyRole(['Logística', 'Administración', 'Sistemas']))
        <flux:card><flux:heading size="lg">Proveedores</flux:heading><form wire:submit="crearProveedor" class="mt-3 grid gap-3 sm:grid-cols-3"><flux:input label="Nombre" wire:model="proveedorNombre" /><flux:input label="RUC / DNI" wire:model="proveedorDocumento" /><div class="flex items-end"><flux:button type="submit" variant="primary">Registrar proveedor</flux:button></div></form><div class="mt-3 flex flex-wrap gap-2">@foreach ($proveedores as $proveedor)<flux:badge>{{ $proveedor->nombre }}{{ $proveedor->documento ? ' · ' . $proveedor->documento : '' }}</flux:badge>@endforeach</div></flux:card>
    @endif
    @if ($tramite->autorizacionesCompra->isNotEmpty())
        <flux:card><flux:heading size="lg">Autorizaciones de compra</flux:heading><div class="mt-3 space-y-3">@foreach ($tramite->autorizacionesCompra as $autorizacion)<div class="rounded border p-3"><div class="flex flex-wrap items-center justify-between gap-3"><div><flux:badge>{{ $autorizacion->estado }}</flux:badge><span class="ms-2 text-sm text-zinc-500">{{ $autorizacion->fecha?->format('d/m/Y H:i') }}</span></div>@if (auth()->user()->hasRole('Administración') && $autorizacion->estado === 'Autorizada')<flux:button size="sm" variant="danger" wire:click="anularAutorizacion({{ $autorizacion->id }})">Anular autorización</flux:button>@endif</div><div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $autorizacion->items->count() }} ítems autorizados</div></div>@endforeach</div></flux:card>
    @endif
    @if (auth()->user()->hasRole('Logística'))
        <flux:card><flux:heading size="lg">Nueva cotización</flux:heading><form wire:submit="crearCotizacion($proveedorSeleccionado)" class="mt-3 space-y-3"><div class="grid gap-3 sm:grid-cols-3"><flux:select label="Proveedor" wire:model="proveedorSeleccionado"><option value="">Selecciona un proveedor</option>@foreach ($proveedores as $proveedor)<option value="{{ $proveedor->id }}">{{ $proveedor->nombre }}</option>@endforeach</flux:select><flux:select label="Tipo de sustento" wire:model="tipoSustento"><option value="Cotización">Cotización</option><option value="Proveedor habitual">Proveedor habitual</option></flux:select><flux:input type="date" label="Fecha" wire:model="fecha" /></div><flux:textarea label="Observación" wire:model="observacion" /><flux:input type="file" label="Sustento" wire:model="archivos" multiple /><div class="space-y-3">@foreach ($tramite->items as $item)<div class="grid gap-3 rounded border p-3 sm:grid-cols-3"><div><span class="font-medium">{{ $item->descripcion }}</span><span class="block text-sm text-zinc-500">Máximo: {{ $item->comprar }}</span></div><flux:input type="number" step="0.01" label="Cantidad" wire:model="items.{{ $item->id }}.cantidad" /><flux:input type="number" step="0.01" label="Precio unitario" wire:model="items.{{ $item->id }}.precio_unitario" /></div>@endforeach</div><div class="flex justify-end"><flux:button type="submit" variant="primary">Guardar cotización</flux:button></div></form></flux:card>
    @endif
    <flux:card><flux:heading size="lg">Cotizaciones registradas</flux:heading><div class="mt-3 divide-y divide-zinc-100 dark:divide-zinc-800">@forelse ($tramite->cotizaciones as $cotizacion)<div class="py-3"><div class="flex flex-wrap items-center justify-between gap-3"><div><span class="font-semibold">{{ $cotizacion->proveedor->nombre }}</span><span class="ms-2 text-sm text-zinc-500">{{ $cotizacion->tipo_sustento }} · {{ $cotizacion->fecha?->format('d/m/Y') }}</span></div><div class="flex items-center gap-2"><flux:badge>{{ $cotizacion->estado }}</flux:badge>@if (auth()->user()->hasRole('Logística') && $cotizacion->estado === 'Borrador')<flux:button size="sm" variant="primary" wire:click="enviar({{ $cotizacion->id }})">Enviar a Administración</flux:button>@endif</div></div><div class="mt-2 text-sm text-zinc-500">Total cotizado: S/ {{ number_format($cotizacion->items->sum(fn ($item) => $item->cantidad * $item->precio_unitario), 2) }}</div>@if (auth()->user()->hasRole('Administración') && in_array($cotizacion->estado, ['Enviada a Administración', 'Autorizada']))<div class="mt-3 space-y-2">@foreach ($cotizacion->items as $cotizacionItem)<div class="grid items-center gap-3 sm:grid-cols-3"><span>{{ $cotizacionItem->item->descripcion }} <small class="text-zinc-500">(cotizado: {{ $cotizacionItem->cantidad }})</small></span><flux:input type="number" step="0.01" wire:model="seleccion.{{ $cotizacionItem->id }}" placeholder="Cantidad a autorizar" /><span class="text-sm text-zinc-500">S/ {{ number_format($cotizacionItem->precio_unitario, 2) }}</span></div>@endforeach</div>@endif</div>@empty<flux:text class="py-3">No hay cotizaciones registradas.</flux:text>@endforelse</div>@if (auth()->user()->hasRole('Administración') && $tramite->cotizaciones->isNotEmpty())<div class="mt-3 flex justify-end"><flux:button variant="primary" wire:click="autorizar">Autorizar selección</flux:button></div>@endif</flux:card>
</div>