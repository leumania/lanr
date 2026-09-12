@php
    $usoLabel = fn (string $uso) => match ($uso) {
        'REQ' => 'Requerimientos',
        'SP' => 'Solicitudes de pago',
        default => 'Ambos',
    };
@endphp

<div class="space-y-4">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" class="text-[#142f44]">Catálogo de unidades</flux:heading>
            <flux:text class="mt-1 text-zinc-500">Gestiona las unidades disponibles para Requerimientos y Solicitudes de pago.</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="nuevo" class="shrink-0 !bg-[#142f44] hover:!bg-[#0d2032]">
            Nueva unidad
        </flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" heading="{{ session('status') }}" />
    @endif

    <flux:card class="p-4">
        <div class="mb-3 flex items-center justify-between">
            <div>
                <flux:heading size="lg" class="text-[#142f44]">Unidades registradas</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Visualiza las unidades y administra cada registro cuando lo necesites.</flux:text>
            </div>
            <flux:text class="text-zinc-500">{{ $unidades->count() }} {{ $unidades->count() === 1 ? 'registrada' : 'registradas' }}</flux:text>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                        <th class="pb-2 font-medium">Unidad</th>
                        <th class="pb-2 font-medium">Uso</th>
                        <th class="pb-2 font-medium">Estado</th>
                        <th class="pb-2 font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($unidades as $unidad)
                        <tr>
                            <td class="py-2.5 font-semibold text-[#142f44] dark:text-white">{{ $unidad->abreviatura }}</td>
                            <td class="py-2.5 text-zinc-600 dark:text-zinc-300">{{ $usoLabel($unidad->uso) }}</td>
                            <td class="py-2.5">
                                <flux:badge size="sm" :color="$unidad->active ? 'green' : 'zinc'">
                                    {{ $unidad->active ? 'Activa' : 'Inactiva' }}
                                </flux:badge>
                            </td>
                            <td class="py-2.5">
                                <div class="flex items-center gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil" wire:click="editar({{ $unidad->id }})">
                                        Editar
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        :icon="$unidad->active ? 'no-symbol' : 'check-circle'"
                                        wire:click="toggle({{ $unidad->id }})"
                                        wire:confirm="¿Confirmas {{ $unidad->active ? 'desactivar' : 'activar' }} la unidad {{ $unidad->abreviatura }}?"
                                    >
                                        {{ $unidad->active ? 'Desactivar' : 'Activar' }}
                                    </flux:button>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        wire:click="delete({{ $unidad->id }})"
                                        wire:confirm="¿Eliminar la unidad {{ $unidad->abreviatura }}? Esta acción no se puede deshacer."
                                    >
                                        Eliminar
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-zinc-500">No hay unidades registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    @if ($mostrarFormulario)
        <flux:modal wire:model="mostrarFormulario" name="unidad-formulario" class="max-w-md">
            <form wire:submit="save" class="flex flex-col gap-4">
                <flux:heading size="lg" class="text-[#142f44]">{{ $editingId ? 'Editar unidad' : 'Nueva unidad' }}</flux:heading>
                <flux:text class="text-zinc-500">
                    {{ $editingId ? 'Actualiza la abreviatura o el tipo de trámite donde estará disponible.' : 'Registra la abreviatura y define dónde estará disponible.' }}
                </flux:text>

                <flux:input label="Abreviatura" wire:model="abreviatura" placeholder="Ingrese unidad" maxlength="20" />

                <flux:select label="Disponible en" wire:model="uso">
                    <flux:select.option value="REQ">Requerimientos</flux:select.option>
                    <flux:select.option value="SP">Solicitudes de pago</flux:select.option>
                    <flux:select.option value="AMBOS">Ambos</flux:select.option>
                </flux:select>

                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="cancelar">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary" class="!bg-[#142f44] hover:!bg-[#0d2032]">
                        {{ $editingId ? 'Guardar cambios' : 'Guardar unidad' }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
