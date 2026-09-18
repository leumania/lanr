<div class="space-y-3">
    <div class="flex items-start justify-between gap-3">
        <div>
            <flux:heading size="xl" class="text-[#142f44]">Hola, {{ explode(' ', auth()->user()->name)[0] }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">Revise sus pendientes y acciones por atender.</flux:text>
        </div>

        @if (auth()->user()->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Administración', 'Logística', 'Tesorería', 'Contabilidad', 'Sistemas']))
            <flux:button variant="primary" icon="plus" :href="route('tramites.create')" class="shrink-0 !bg-[#142f44] hover:!bg-[#0d2032]" wire:navigate>
                Nuevo trámite
            </flux:button>
        @endif
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="flex items-center justify-between p-3">
            <div>
                <flux:text class="text-zinc-500">Trámites activos</flux:text>
                <flux:heading size="xl" class="mt-1 text-[#142f44]">{{ $activos }}</flux:heading>
            </div>
            <div class="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400">
                <flux:icon.folder-open class="size-5" />
            </div>
        </flux:card>

        <flux:card class="flex items-center justify-between p-3">
            <div>
                <flux:text class="text-zinc-500">Por aprobar</flux:text>
                <flux:heading size="xl" class="mt-1 text-[#142f44]">{{ $porAprobar }}</flux:heading>
            </div>
            <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
                <flux:icon.clock class="size-5" />
            </div>
        </flux:card>

        <flux:card class="flex items-center justify-between p-3">
            <div>
                <flux:text class="text-zinc-500">{{ $tercerLabel }}</flux:text>
                <flux:heading size="xl" class="mt-1 text-[#142f44]">{{ $tercerValor }}</flux:heading>
            </div>
            <div class="flex size-10 items-center justify-center rounded-xl bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon.building-office class="size-5" />
            </div>
        </flux:card>

        <flux:card class="flex items-center justify-between p-3">
            <div>
                <flux:text class="text-zinc-500">{{ $cuartoLabel }}</flux:text>
                <flux:heading size="xl" class="mt-1 text-[#142f44]">{{ $cuartoValor }}</flux:heading>
            </div>
            <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                <flux:icon.truck class="size-5" />
            </div>
        </flux:card>
    </div>

    <flux:card class="p-3">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg" class="text-[#142f44]">Pendientes de aprobación</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Trámites que requieren su visto bueno.</flux:text>
            </div>

            @if ($pendientesAprobacion->isNotEmpty())
                <flux:text class="text-zinc-500">
                    {{ $pendientesAprobacion->count() }} {{ $pendientesAprobacion->count() === 1 ? 'pendiente' : 'pendientes' }}
                </flux:text>
            @endif
        </div>

        @if ($pendientesAprobacion->isNotEmpty())
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-100 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                            <th class="pb-2 font-medium">Código</th>
                            <th class="pb-2 font-medium">Tipo</th>
                            <th class="pb-2 font-medium">N°</th>
                            <th class="pb-2 font-medium">Aprobación</th>
                            <th class="pb-2 font-medium">Creado por</th>
                            <th class="pb-2 font-medium">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($pendientesAprobacion as $approval)
                            <tr>
                                <td class="py-2.5 font-semibold text-[#142f44] dark:text-white">{{ $approval->tramite->tracking }}</td>
                                <td class="py-2.5">
                                    <flux:badge size="sm" :color="$approval->tramite->tipo === 'REQ' ? 'blue' : 'purple'" inset="top bottom">
                                        {{ $approval->tramite->tipo === 'REQ' ? 'Requerimiento' : 'Solicitud de Pago' }}
                                    </flux:badge>
                                </td>
                                <td class="py-2.5 text-zinc-600 dark:text-zinc-300">{{ $approval->tramite->numero }}</td>
                                <td class="py-2.5 text-zinc-600 dark:text-zinc-300">{{ $approval->rol }}</td>
                                <td class="py-2.5 text-zinc-600 dark:text-zinc-300">{{ $approval->tramite->creador?->name }}</td>
                                <td class="py-2.5">
                                    <flux:button size="sm" variant="ghost" icon="eye" :href="route('tramites.show', $approval->tramite)" wire:navigate>
                                        Revisar
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="mt-3 flex flex-col items-center gap-2 py-4 text-center">
                <flux:icon.check-circle class="size-8 text-emerald-500" />
                <flux:text class="font-semibold text-[#142f44] dark:text-white">No tienes aprobaciones pendientes</flux:text>
                <flux:text class="text-zinc-500">Todos los trámites asignados a ti están atendidos.</flux:text>
            </div>
        @endif
    </flux:card>

    @if (auth()->user()->hasRole('Logística'))
        <flux:card class="p-3">
            <flux:heading size="lg" class="text-[#142f44]">Pendientes de Logística</flux:heading>
            <div class="mt-3 flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($pendientesLogistica as $t)
                    <a href="{{ route('tramites.show', $t) }}" wire:navigate class="flex items-center justify-between py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div>
                            <div class="font-semibold text-[#142f44] dark:text-white">{{ $t->tracking }}</div>
                            <div class="text-sm text-zinc-500">{{ $t->creador?->name }}</div>
                        </div>
                        <flux:badge size="sm" color="blue">{{ $t->pendiente_de }}</flux:badge>
                    </a>
                @empty
                    <flux:text class="py-3 text-zinc-500">No hay requerimientos pendientes de Logística.</flux:text>
                @endforelse
            </div>
        </flux:card>
    @endif

    @if (auth()->user()->hasRole('Gerencia General'))
        <flux:card class="p-3">
            <flux:heading size="lg" class="text-[#142f44]">Solicitudes pendientes de asignar pago</flux:heading>
            <div class="mt-3 flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($spPendientesAsignacion as $t)
                    <a href="{{ route('tramites.show', $t) }}" wire:navigate class="flex items-center justify-between py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div>
                            <div class="font-semibold text-[#142f44] dark:text-white">{{ $t->tracking }}</div>
                            <div class="text-sm text-zinc-500">{{ $t->creador?->name }} · S/ {{ number_format($t->abono, 2) }}</div>
                        </div>
                        <flux:badge size="sm" color="purple">Asignar pago</flux:badge>
                    </a>
                @empty
                    <flux:text class="py-3 text-zinc-500">No hay solicitudes pendientes de asignación.</flux:text>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="p-3">
            <flux:heading size="lg" class="text-[#142f44]">Solicitudes pendientes de conformidad final</flux:heading>
            <div class="mt-3 flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($spPendientesConformidad as $t)
                    <a href="{{ route('tramites.show', $t) }}" wire:navigate class="flex items-center justify-between py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div>
                            <div class="font-semibold text-[#142f44] dark:text-white">{{ $t->tracking }}</div>
                            <div class="text-sm text-zinc-500">{{ $t->creador?->name }} · Pagado: S/ {{ number_format($t->gestionSp->monto_pagado ?? 0, 2) }}</div>
                        </div>
                        <flux:badge size="sm" color="yellow">Confirmar</flux:badge>
                    </a>
                @empty
                    <flux:text class="py-3 text-zinc-500">No hay solicitudes pendientes de conformidad.</flux:text>
                @endforelse
            </div>
        </flux:card>
    @endif

    @if (auth()->user()->hasRole('Tesorería'))
        <flux:card class="p-3">
            <flux:heading size="lg" class="text-[#142f44]">Solicitudes de pago asignadas a Tesorería</flux:heading>
            <div class="mt-3 flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($spTesoreria as $t)
                    <a href="{{ route('tramites.show', $t) }}" wire:navigate class="flex items-center justify-between py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div>
                            <div class="font-semibold text-[#142f44] dark:text-white">{{ $t->tracking }}</div>
                            <div class="text-sm text-zinc-500">{{ $t->creador?->name }} · S/ {{ number_format($t->abono, 2) }}</div>
                        </div>
                        <flux:badge size="sm" color="purple">Registrar pago</flux:badge>
                    </a>
                @empty
                    <flux:text class="py-3 text-zinc-500">No hay solicitudes asignadas a Tesorería.</flux:text>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="p-3">
            <div class="flex items-center justify-between">
                <flux:heading size="lg" class="text-[#142f44]">Reembolsos pendientes de Tesorería</flux:heading>
                @if ($reembolsosPendientes->isNotEmpty())
                    <flux:text class="text-zinc-500">
                        {{ $reembolsosPendientes->count() }} {{ $reembolsosPendientes->count() === 1 ? 'pendiente' : 'pendientes' }}
                    </flux:text>
                @endif
            </div>
            <div class="mt-3 flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($reembolsosPendientes as $reembolso)
                    <a href="{{ route('admin.treasury') }}" wire:navigate class="flex items-center justify-between py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div>
                            <div class="font-semibold text-[#142f44] dark:text-white">{{ $reembolso->numero }}</div>
                            <div class="text-sm text-zinc-500">{{ $reembolso->solicitante?->name }} · {{ $reembolso->moneda }} {{ number_format($reembolso->monto, 2) }}</div>
                        </div>
                        <flux:badge size="sm" color="emerald">Atender</flux:badge>
                    </a>
                @empty
                    <flux:text class="py-3 text-zinc-500">No hay reembolsos pendientes de atención.</flux:text>
                @endforelse
            </div>
        </flux:card>
    @endif
</div>
