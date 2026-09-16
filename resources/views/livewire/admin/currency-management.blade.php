<div class="space-y-3">
    <div class="flex items-start justify-between gap-3">
        <div>
            <flux:heading size="xl" class="text-[#142f44]">Catálogo de monedas</flux:heading>
            <flux:text class="mt-1 text-zinc-500">Gestiona las monedas disponibles en órdenes y demás trámites.</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="nuevo" class="shrink-0 !bg-[#142f44] hover:!bg-[#0d2032]">
            Nueva moneda
        </flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" heading="{{ session('status') }}" />
    @endif

    <flux:card class="p-3">
        <div class="mb-3 flex items-center justify-between">
            <div>
                <flux:heading size="lg" class="text-[#142f44]">Monedas registradas</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Visualiza las monedas y administra cada registro cuando lo necesites.</flux:text>
            </div>
            <flux:text class="text-zinc-500">{{ $monedas->count() }} {{ $monedas->count() === 1 ? 'registrada' : 'registradas' }}</flux:text>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                        <th class="pb-2 font-medium">Código</th>
                        <th class="pb-2 font-medium">Nombre</th>
                        <th class="pb-2 font-medium">Símbolo</th>
                        <th class="pb-2 font-medium">Estado</th>
                        <th class="pb-2 font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($monedas as $moneda)
                        <tr>
                            <td class="py-2.5 font-semibold text-[#142f44] dark:text-white">{{ $moneda->codigo }}</td>
                            <td class="py-2.5 text-zinc-600 dark:text-zinc-300">{{ $moneda->nombre }}</td>
                            <td class="py-2.5 text-zinc-600 dark:text-zinc-300">{{ $moneda->simbolo }}</td>
                            <td class="py-2.5">
                                <flux:badge size="sm" :color="$moneda->active ? 'green' : 'zinc'">
                                    {{ $moneda->active ? 'Activa' : 'Inactiva' }}
                                </flux:badge>
                            </td>
                            <td class="py-2.5">
                                <div class="flex items-center gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil" wire:click="editar({{ $moneda->id }})">
                                        Editar
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        :icon="$moneda->active ? 'no-symbol' : 'check-circle'"
                                        wire:click="toggle({{ $moneda->id }})"
                                        wire:confirm="¿Confirmas {{ $moneda->active ? 'desactivar' : 'activar' }} la moneda {{ $moneda->codigo }}?"
                                    >
                                        {{ $moneda->active ? 'Desactivar' : 'Activar' }}
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="delete({{ $moneda->id }})"
                                        wire:confirm="¿Eliminar la moneda {{ $moneda->codigo }}? Esta acción no se puede deshacer."
                                    >
                                        Eliminar
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-zinc-500">No hay monedas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    @if ($mostrarFormulario)
        <flux:modal wire:model="mostrarFormulario" name="moneda-formulario" class="max-w-md">
            <form wire:submit="save" class="flex flex-col gap-3">
                <flux:heading size="lg" class="text-[#142f44]">{{ $editingId ? 'Editar moneda' : 'Nueva moneda' }}</flux:heading>
                <flux:text class="text-zinc-500">
                    {{ $editingId ? 'Actualiza el código, nombre o símbolo de la moneda.' : 'Registra el código, nombre y símbolo de la nueva moneda.' }}
                </flux:text>

                <flux:input label="Código" wire:model="codigo" placeholder="Ej: PEN" maxlength="10" />
                <flux:input label="Nombre" wire:model="nombre" placeholder="Ej: Soles" maxlength="50" />
                <flux:input label="Símbolo" wire:model="simbolo" placeholder="Ej: S/" maxlength="10" />

                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="cancelar">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary" class="!bg-[#142f44] hover:!bg-[#0d2032]">
                        {{ $editingId ? 'Guardar cambios' : 'Guardar moneda' }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
