<?php

use App\Models\Approval;
use App\Models\Obra;
use App\Models\Tramite;
use Livewire\Component;

new class extends Component
{
    public function getResumenObrasProperty()
    {
        return Obra::where('activa', true)->orderBy('nombre')->get()->map(function (Obra $obra) {
            $base = Tramite::where('obra_id', $obra->id);

            return [
                'obra' => $obra,
                'activos' => (clone $base)->where('estado', '!=', 'Cerrado')->count(),
                'aprobaciones' => Approval::where('usuario_id', auth()->id())
                    ->where('aprobado', false)
                    ->whereHas('tramite', fn ($q) => $q->where('obra_id', $obra->id))
                    ->count(),
                'oficina' => (clone $base)->whereIn('estado', ['En oficina', 'Cotización', 'Comprado'])->count(),
                'recibir' => (clone $base)->where('estado', 'Enviado a obra')->count(),
            ];
        })->filter(fn (array $resumen) => ($resumen['activos'] + $resumen['aprobaciones'] + $resumen['oficina'] + $resumen['recibir']) > 0);
    }

    public function getActivosProperty(): int
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Sistemas'])) {
            return Tramite::where('estado', '!=', 'Cerrado')->count();
        }

        return Tramite::where('estado', '!=', 'Cerrado')
            ->where(function ($q) {
                $q->where('tipo', '!=', 'REQ')
                    ->orWhere(function ($sub) {
                        $sub->where('tipo', 'REQ')
                            ->whereHas('approvals', fn ($a) => $a->where('aprobado', true), '=', 2);
                    });
            })
            ->count();
    }

    public function getAprobProperty(): int
    {
        return Approval::where('usuario_id', auth()->id())
            ->where('aprobado', false)
            ->count();
    }

    public function getOficinaProperty(): int
    {
        return Tramite::whereIn('estado', ['En oficina', 'Cotización', 'Comprado'])->count();
    }

    public function getRecibirProperty(): int
    {
        return Tramite::where('estado', 'Enviado a obra')->count();
    }

    public function getPendientesAprobacionProperty()
    {
        return Approval::with(['tramite.creador'])
            ->where('usuario_id', auth()->id())
            ->where('aprobado', false)
            ->whereHas('tramite')
            ->get()
            ->sortByDesc(fn ($a) => $a->tramite->id);
    }
    
    public function getSpPendientesAsignacionProperty()
    {
        if (auth()->user()->username !== 'lNeyra') {
            return collect();
        }

        return Tramite::with('creador')
            ->where('tipo', 'SP')
            ->where('estado', 'Pendiente asignación de pago')
            ->latest('id')
            ->get();
    }

    public function getSpPendientesConformidadProperty()
    {
        if (auth()->user()->username !== 'lNeyra') {
            return collect();
        }

        return Tramite::with(['creador', 'gestionSp'])
            ->where('tipo', 'SP')
            ->where('estado', 'Pagada pendiente conformidad GG')
            ->whereHas('gestionSp', fn ($q) => $q->where('conformidad_gg', false))
            ->latest('id')
            ->get();
    }

        public function getPendientesLogisticaProperty()
    {
        if (! auth()->user()->hasRole('Logística')) {
            return collect();
        }

        return Tramite::with(['creador', 'gestionLogistica'])
            ->where('tipo', 'REQ')
            ->whereIn('estado', ['Aprobado', 'Recibido por Logística', 'En gestión de compra'])
            ->latest('id')
            ->get()
            ->map(function ($t) {
                $g = $t->gestionLogistica;

                $t->pendiente_de = match (true) {
                    $t->estado === 'Aprobado' => 'Recepcionar',
                    $t->estado === 'Recibido por Logística' => 'Cotización / compra',
                    $t->estado === 'En gestión de compra' && blank($g?->estado_pago) => 'Pago',
                    in_array($g?->estado_pago, ['Pagado por Logística', 'Pagado por Tesorería']) => 'Guía y envío a obra',
                    default => 'Revisar',
                };

                return $t;
            });
    }

    public function getSpTesoreriaProperty()
    {
        if (auth()->user()->username !== 'yCoronado') {
            return collect();
        }

        return Tramite::with(['creador', 'gestionSp'])
            ->where('tipo', 'SP')
            ->where('estado', 'Asignada a Tesorería')
            ->latest('id')
            ->get();
    }
};
?>
<div class="flex h-full w-full flex-1 flex-col gap-4">
    @forelse ($this->resumenObras as $resumen)
        <div class="flex flex-col gap-3">
            <flux:heading size="lg">{{ $resumen['obra']->nombre }}</flux:heading>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('tramites.index', ['filtro' => 'activos', 'obra' => $resumen['obra']->id]) }}" wire:navigate class="block">
                    <flux:card class="h-full transition hover:border-emerald-400"><flux:text class="text-zinc-500">Activos</flux:text><flux:heading size="xl">{{ $resumen['activos'] }}</flux:heading></flux:card>
                </a>
                <a href="{{ route('tramites.index', ['filtro' => 'aprobaciones', 'obra' => $resumen['obra']->id]) }}" wire:navigate class="block">
                    <flux:card class="h-full transition hover:border-amber-400 {{ $resumen['aprobaciones'] > 0 ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-200 dark:border-amber-500 dark:bg-amber-950/30 dark:ring-amber-900' : '' }}"><flux:text class="text-zinc-500">Pendientes de mi aprobación</flux:text><flux:heading size="xl" class="{{ $resumen['aprobaciones'] > 0 ? 'text-amber-700 dark:text-amber-300' : '' }}">{{ $resumen['aprobaciones'] }}</flux:heading></flux:card>
                </a>
                <a href="{{ route('tramites.index', ['filtro' => 'oficina', 'obra' => $resumen['obra']->id]) }}" wire:navigate class="block">
                    <flux:card class="h-full transition hover:border-emerald-400"><flux:text class="text-zinc-500">En oficina</flux:text><flux:heading size="xl">{{ $resumen['oficina'] }}</flux:heading></flux:card>
                </a>
                <a href="{{ route('tramites.index', ['filtro' => 'recibir', 'obra' => $resumen['obra']->id]) }}" wire:navigate class="block">
                    <flux:card class="h-full transition hover:border-emerald-400 {{ $resumen['recibir'] > 0 ? 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-200 dark:border-emerald-500 dark:bg-emerald-950/30 dark:ring-emerald-900' : '' }}"><flux:text class="text-zinc-500">Por recibir en obra</flux:text><flux:heading size="xl" class="{{ $resumen['recibir'] > 0 ? 'text-emerald-700 dark:text-emerald-300' : '' }}">{{ $resumen['recibir'] }}</flux:heading></flux:card>
                </a>
            </div>
        </div>
    @empty
        <flux:card><flux:text class="text-zinc-500">No hay pendientes registrados en las obras activas.</flux:text></flux:card>
    @endforelse

    <div class="hidden grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('tramites.index', ['filtro' => 'activos']) }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-emerald-400">
                <flux:text class="text-zinc-500">Activos</flux:text>
                <flux:heading size="xl">{{ $this->activos }}</flux:heading>
            </flux:card>
        </a>

        <a href="{{ route('tramites.index', ['filtro' => 'aprobaciones']) }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-amber-400 {{ $this->aprob > 0 ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-200 dark:border-amber-500 dark:bg-amber-950/30 dark:ring-amber-900' : '' }}">
                <flux:text class="text-zinc-500">Pendientes de mi aprobación</flux:text>
                <flux:heading size="xl" class="{{ $this->aprob > 0 ? 'text-amber-700 dark:text-amber-300' : '' }}">{{ $this->aprob }}</flux:heading>
            </flux:card>
        </a>

        <a href="{{ route('tramites.index', ['filtro' => 'oficina']) }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-emerald-400">
                <flux:text class="text-zinc-500">En oficina</flux:text>
                <flux:heading size="xl">{{ $this->oficina }}</flux:heading>
            </flux:card>
        </a>

        <a href="{{ route('tramites.index', ['filtro' => 'recibir']) }}" wire:navigate class="block">
            <flux:card class="h-full transition hover:border-emerald-400 {{ $this->recibir > 0 ? 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-200 dark:border-emerald-500 dark:bg-emerald-950/30 dark:ring-emerald-900' : '' }}">
                <flux:text class="text-zinc-500">Por recibir en obra</flux:text>
                <flux:heading size="xl" class="{{ $this->recibir > 0 ? 'text-emerald-700 dark:text-emerald-300' : '' }}">{{ $this->recibir }}</flux:heading>
            </flux:card>
        </a>
    </div>

    <flux:card class="flex-1">
        <flux:heading size="lg" class="mb-3">Pendientes de mi aprobación</flux:heading>

        <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($this->pendientesAprobacion as $approval)
                
                    <a href="{{ route('tramites.show', $approval->tramite) }}" wire:navigate class="flex items-center justify-between py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                    <div>
                        <div class="font-semibold text-zinc-900 dark:text-white">{{ $approval->tramite->tracking }}</div>
                        <div class="text-sm text-zinc-500">
                            {{ $approval->tramite->creador->name }} · V°B° requerido: {{ $approval->rol }}
                        </div>
                    </div>
                    <flux:badge size="sm">{{ $approval->tramite->estado }}</flux:badge>
                </a>
            @empty
                <flux:text class="py-4 text-zinc-500">No tienes trámites pendientes de aprobación.</flux:text>
            @endforelse
        </div>
    </flux:card>

    @if (auth()->user()->username === 'lNeyra')
        <flux:card class="flex-1">
            <flux:heading size="lg" class="mb-3">Solicitudes pendientes de asignar pago</flux:heading>
            <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->spPendientesAsignacion as $t)
                    <a href="{{ route('tramites.show', $t) }}" wire:navigate class="flex items-center justify-between py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-white">{{ $t->tracking }}</div>
                            <div class="text-sm text-zinc-500">{{ $t->creador->name }} · S/ {{ number_format($t->abono, 2) }}</div>
                        </div>
                        <flux:badge size="sm" color="purple">Asignar pago</flux:badge>
                    </a>
                @empty
                    <flux:text class="py-4 text-zinc-500">No hay solicitudes pendientes de asignación.</flux:text>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="flex-1">
            <flux:heading size="lg" class="mb-3">Solicitudes pendientes de conformidad final</flux:heading>
            <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->spPendientesConformidad as $t)
                    <a href="{{ route('tramites.show', $t) }}" wire:navigate class="flex items-center justify-between py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-white">{{ $t->tracking }}</div>
                            <div class="text-sm text-zinc-500">{{ $t->creador->name }} · Pagado: S/ {{ number_format($t->gestionSp->monto_pagado, 2) }}</div>
                        </div>
                        <flux:badge size="sm" color="yellow">Confirmar</flux:badge>
                    </a>
                @empty
                    <flux:text class="py-4 text-zinc-500">No hay solicitudes pendientes de conformidad.</flux:text>
                @endforelse
            </div>
        </flux:card>
    @endif

        @if (auth()->user()->hasRole('Logística'))
        <flux:card class="flex-1">
            <flux:heading size="lg" class="mb-3">Pendientes de Logística</flux:heading>
            <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->pendientesLogistica as $t)
                    <a href="{{ route('tramites.show', $t) }}" wire:navigate class="flex items-center justify-between py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-white">{{ $t->tracking }}</div>
                            <div class="text-sm text-zinc-500">{{ $t->creador->name }}</div>
                        </div>
                        <flux:badge size="sm" color="blue">{{ $t->pendiente_de }}</flux:badge>
                    </a>
                @empty
                    <flux:text class="py-4 text-zinc-500">No hay requerimientos pendientes de Logística.</flux:text>
                @endforelse
            </div>
        </flux:card>
    @endif

    @if (auth()->user()->username === 'yCoronado')
        <flux:card class="flex-1">
            <flux:heading size="lg" class="mb-3">Solicitudes asignadas a Tesorería</flux:heading>
            <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->spTesoreria as $t)
                    <a href="{{ route('tramites.show', $t) }}" wire:navigate class="flex items-center justify-between py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-white">{{ $t->tracking }}</div>
                            <div class="text-sm text-zinc-500">{{ $t->creador->name }} · S/ {{ number_format($t->abono, 2) }}</div>
                        </div>
                        <flux:badge size="sm" color="purple">Registrar pago</flux:badge>
                    </a>
                @empty
                    <flux:text class="py-4 text-zinc-500">No hay solicitudes asignadas a Tesorería.</flux:text>
                @endforelse
            </div>
        </flux:card>
    @endif
</div>