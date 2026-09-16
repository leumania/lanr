@php
    $tabs = [
        ['key' => 'pendientes', 'icon' => 'paper-airplane', 'title' => 'Pendientes de enviar', 'subtitle' => 'Mis trámites sin enviar'],
        ['key' => 'aprobar', 'icon' => 'user', 'title' => 'Por aprobar', 'subtitle' => 'Pendientes de V°B° en obra'],
        ['key' => 'proceso', 'icon' => 'arrow-path', 'title' => 'En proceso', 'subtitle' => 'En gestión de oficina'],
        ['key' => 'finalizados', 'icon' => 'check-circle', 'title' => 'Finalizados', 'subtitle' => 'Trámites completados'],
        ['key' => 'todos', 'icon' => 'list-bullet', 'title' => 'Todos', 'subtitle' => 'Todos los trámites visibles'],
    ];
@endphp

<div class="space-y-3">
    <div>
        <flux:heading size="xl" class="text-[#142f44]">Trámites</flux:heading>
        <flux:text class="mt-1 text-zinc-500">Consulta y seguimiento de requerimientos y solicitudes de pago.</flux:text>
    </div>

    <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($tabs as $tab)
            @php $activo = $flujo === $tab['key']; @endphp
            <button
                type="button"
                wire:click="setFlujo('{{ $tab['key'] }}')"
                @class([
                    'relative flex min-h-[68px] items-center gap-2.5 rounded-lg border p-2.5 text-left transition',
                    'border-[#142f44] bg-[#142f44] text-white' => $activo,
                    'border-zinc-200 bg-white hover:border-[#142f44]/30 dark:border-zinc-700 dark:bg-zinc-800' => ! $activo,
                ])
            >
                <div @class([
                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                    'bg-white/15 text-white' => $activo,
                    'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400' => ! $activo,
                ])>
                    <flux:icon :icon="$tab['icon']" class="size-3.5" />
                </div>

                <div class="min-w-0 leading-tight">
                    <div @class(['truncate text-xs font-semibold', 'text-white' => $activo, 'text-[#142f44] dark:text-white' => ! $activo])>
                        {{ $tab['title'] }}
                    </div>
                    <div @class(['truncate text-[10px]', 'text-white/70' => $activo, 'text-zinc-500' => ! $activo])>
                        {{ $tab['subtitle'] }}
                    </div>
                </div>

                <span @class([
                    'absolute top-2 right-2 flex size-5 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold',
                    'bg-white/15 text-white' => $activo,
                    'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' => ! $activo,
                ])>
                    {{ $conteos[$tab['key']] }}
                </span>
            </button>
        @endforeach
    </div>

    <flux:card class="p-3">
        <div class="grid gap-3 sm:grid-cols-[minmax(0,220px)_1fr]">
            <flux:select label="Tipo de trámite" wire:model.live="tipo">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="REQ">Requerimiento</flux:select.option>
                <flux:select.option value="SP">Solicitud de pago</flux:select.option>
            </flux:select>

            <div>
                <flux:label>Buscar</flux:label>
                <div class="mt-1 flex gap-2">
                    <flux:input
                        wire:model="search"
                        wire:keydown.enter="buscar"
                        icon="magnifying-glass"
                        class="flex-1"
                        placeholder="Ingrese N.° de seguimiento o N.° de formato"
                    />
                    <flux:button variant="primary" icon="magnifying-glass" wire:click="buscar" class="!bg-[#142f44] hover:!bg-[#0d2032]">
                        Buscar
                    </flux:button>
                    <flux:button variant="ghost" icon="arrow-uturn-left" wire:click="limpiar">
                        Limpiar
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:card>

    <flux:card class="p-3">
        <div>
            <flux:heading size="lg" class="text-[#142f44]">Listado de trámites</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ $nota }}</flux:text>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                        <th class="pb-2 font-medium">N° seguimiento</th>
                        <th class="pb-2 font-medium">N° formato</th>
                        <th class="pb-2 font-medium">Tipo de trámite</th>
                        <th class="pb-2 font-medium">Fecha</th>
                        <th class="pb-2 font-medium">Estado</th>
                        <th class="pb-2 font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($tramites as $tramite)
                        <tr>
                            <td class="py-2.5 pe-4 font-semibold text-[#142f44] dark:text-white">{{ $tramite->tracking }}</td>
                            <td class="py-2.5 pe-4">
                                <div class="font-medium text-zinc-700 dark:text-zinc-200">{{ $tramite->numero }}</div>
                                @if ($tramite->formato)
                                    <div class="text-xs text-zinc-400">{{ $tramite->formato }}</div>
                                @endif
                            </td>
                            <td class="py-2.5 pe-4">
                                <flux:badge size="sm" :icon="$tramite->tipo === 'REQ' ? 'lock-closed' : 'credit-card'" color="blue">
                                    {{ $tramite->tipo === 'REQ' ? 'Requerimiento' : 'Solicitud de pago' }}
                                </flux:badge>
                            </td>
                            <td class="py-2.5 pe-4 text-zinc-600 dark:text-zinc-300">{{ $tramite->fecha?->format('Y-m-d') }}</td>
                            <td class="py-2.5 pe-4">
                                <flux:badge size="sm" color="blue">
                                    {{ $tramite->estado === 'Pendiente de aprobación' ? 'Pendiente de V°B°' : $tramite->estado }}
                                </flux:badge>
                            </td>
                            <td class="py-2.5">
                                <flux:button size="sm" variant="ghost" icon="eye" :href="route('tramites.show', $tramite)" wire:navigate>
                                    Detalle
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-center text-zinc-500">No hay trámites para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tramites->total() > 0)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <flux:text class="text-zinc-500">
                    Mostrando {{ $tramites->firstItem() }} a {{ $tramites->lastItem() }} de {{ $tramites->total() }}
                    {{ $tramites->total() === 1 ? 'trámite' : 'trámites' }}
                </flux:text>

                <div class="flex items-center gap-2">
                    <flux:button size="sm" variant="ghost" icon="chevron-left" :disabled="! $tramites->onFirstPage()" wire:click="previousPage">
                        Anterior
                    </flux:button>

                    @for ($page = 1; $page <= $tramites->lastPage(); $page++)
                        <flux:button
                            size="sm"
                            :variant="$page === $tramites->currentPage() ? 'primary' : 'ghost'"
                            wire:click="gotoPage({{ $page }})"
                            class="{{ $page === $tramites->currentPage() ? '!bg-[#142f44] hover:!bg-[#0d2032]' : '' }}"
                        >
                            {{ $page }}
                        </flux:button>
                    @endfor

                    <flux:button size="sm" variant="ghost" icon:trailing="chevron-right" :disabled="! $tramites->hasMorePages()" wire:click="nextPage">
                        Siguiente
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:card>
</div>
