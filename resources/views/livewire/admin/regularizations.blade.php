@php
    $card = fn ($r) => [
        'tracking' => $r->tramite->tracking,
        'numero' => $r->tramite->numero,
        'creador' => $r->tramite->creador?->name,
        'comprobantes' => $r->detalles->count(),
        'total' => $r->detalles->sum('precio_total'),
        'fecha' => $r->fecha_regularizacion?->format('d/m/Y'),
    ];
@endphp

<div class="space-y-4">
    <div>
        <flux:heading size="xl" class="text-[#142f44]">Regularizaciones</flux:heading>
        <flux:text class="mt-1 text-zinc-500">Complete posteriormente el detalle de las compras realizadas por Logística.</flux:text>
    </div>

    <flux:card class="p-4">
        <flux:heading size="lg" class="text-[#142f44]">Pendientes</flux:heading>

        @if ($pendientes->isNotEmpty())
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($pendientes as $regularizacion)
                    @php $datos = $card($regularizacion); @endphp
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="font-semibold text-[#142f44] dark:text-white">{{ $datos['tracking'] }}</div>
                        <div class="mt-0.5 text-sm text-zinc-500">N° {{ $datos['numero'] }} · Creado por {{ $datos['creador'] }}</div>

                        <flux:badge size="sm" color="amber" icon="clock" class="mt-2">
                            Pendiente de regularización
                        </flux:badge>

                        <div class="mt-3 flex items-center justify-between text-sm">
                            <div>
                                <div class="text-zinc-500">Comprobantes</div>
                                <div class="font-semibold text-[#142f44] dark:text-white">{{ $datos['comprobantes'] }}</div>
                            </div>
                            <div class="text-end">
                                <div class="text-zinc-500">Total registrado</div>
                                <div class="font-semibold text-[#142f44] dark:text-white">S/ {{ number_format($datos['total'], 2) }}</div>
                            </div>
                        </div>

                        <flux:button class="mt-3 w-full" size="sm" variant="primary" icon="clipboard-document-check" :href="route('tramites.show', $regularizacion->tramite)" wire:navigate>
                            Regularizar
                        </flux:button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="mt-4 flex flex-col items-center gap-2 py-8 text-center">
                <flux:icon.check-circle class="size-8 text-emerald-500" />
                <flux:text class="text-zinc-500">No tienes regularizaciones de compra pendientes.</flux:text>
            </div>
        @endif
    </flux:card>

    @if ($completadas->isNotEmpty())
        <flux:card class="p-4">
            <flux:heading size="lg" class="text-[#142f44]">Completadas recientemente</flux:heading>

            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($completadas as $regularizacion)
                    @php $datos = $card($regularizacion); @endphp
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="font-semibold text-[#142f44] dark:text-white">{{ $datos['tracking'] }}</div>
                        <div class="mt-0.5 text-sm text-zinc-500">N° {{ $datos['numero'] }} · {{ $datos['fecha'] }}</div>

                        <div class="mt-3 flex items-center justify-between text-sm">
                            <div>
                                <div class="text-zinc-500">Comprobantes</div>
                                <div class="font-semibold text-[#142f44] dark:text-white">{{ $datos['comprobantes'] }}</div>
                            </div>
                            <div class="text-end">
                                <div class="text-zinc-500">Total registrado</div>
                                <div class="font-semibold text-[#142f44] dark:text-white">S/ {{ number_format($datos['total'], 2) }}</div>
                            </div>
                        </div>

                        <flux:button class="mt-3 w-full" size="sm" variant="ghost" icon="eye" :href="route('tramites.show', $regularizacion->tramite)" wire:navigate>
                            Ver
                        </flux:button>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
