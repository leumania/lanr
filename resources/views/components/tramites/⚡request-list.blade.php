<?php

use App\Models\Tramite;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $tipo = 'Todos';
    public string $buscar = '';
    public string $filtro = 'todos';
    public ?int $obraId = null;

    public function mount(): void
    {
        $this->filtro = request()->query('filtro', 'todos');
        $this->obraId = request()->integer('obra') ?: null;
    }

    public function updatingTipo(): void
    {
        $this->resetPage();
    }

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function estadoColor(string $estado): string
    {
        return match ($estado) {
            'Pendiente de aprobación' => 'yellow',
            'En gestión de compra', 'En oficina', 'Cotización', 'Comprado' => 'blue',
            'Enviado a obra' => 'purple',
            'Cerrado' => 'green',
            default => 'zinc',
        };
    }

    public function getTramitesProperty()
    {
        return Tramite::with(['creador', 'obra'])
            ->when($this->obraId, fn ($q) => $q->where('obra_id', $this->obraId))
            ->when($this->tipo !== 'Todos', fn ($q) => $q->where('tipo', $this->tipo))
            ->when($this->filtro === 'activos', fn ($q) => $q->where('estado', '!=', 'Cerrado'))
            ->when($this->filtro === 'aprobaciones', fn ($q) => $q->whereHas('approvals', fn ($approval) => $approval->where('usuario_id', auth()->id())->where('aprobado', false)))
            ->when($this->filtro === 'oficina', fn ($q) => $q->whereIn('estado', ['En oficina', 'Cotización', 'Comprado']))
            ->when($this->filtro === 'recibir', fn ($q) => $q->where('estado', 'Enviado a obra'))
            ->when($this->buscar !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('tracking', 'like', "%{$this->buscar}%")
                        ->orWhere('numero', 'like', "%{$this->buscar}%");
                });
            })
            ->latest()
            ->paginate(10);
    }

    public function with(): array
    {
        return [
            'tramites' => $this->tramites,
        ];
    }
};
?>
<div class="p-6">
    <flux:heading size="xl">Trámites</flux:heading>
    <flux:text class="mb-6 text-zinc-500">Consulta requerimientos y solicitudes de pago.</flux:text>

    @if ($filtro !== 'todos')
        <div class="mb-4 flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900 dark:bg-emerald-950/30">
            <flux:text class="text-emerald-800 dark:text-emerald-200">
                Filtro activo:
                <strong>{{ match ($filtro) { 'activos' => 'Trámites activos', 'aprobaciones' => 'Pendientes de mi aprobación', 'oficina' => 'En oficina', 'recibir' => 'Por recibir en obra', default => 'Todos' } }}</strong>
            </flux:text>
            <flux:button size="sm" variant="ghost" href="{{ route('tramites.index') }}" wire:navigate>Ver todos</flux:button>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <flux:select label="Tipo de trámite" wire:model.live="tipo">
            <flux:select.option value="Todos">Todos</flux:select.option>
            <flux:select.option value="REQ">Requerimiento</flux:select.option>
            <flux:select.option value="SP">Solicitud de Pago</flux:select.option>
        </flux:select>

        <flux:input
            label="Buscar"
            wire:model.live.debounce.400ms="buscar"
            icon="magnifying-glass"
            placeholder="Escribe N°, código o parte del código..."
        />
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Obra</th>
                    <th class="px-4 py-3">N°</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Creado por</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($tramites as $t)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">{{ $t->tracking }}</td>
                        <td class="px-4 py-3">{{ $t->obra->nombre ?? 'Sin obra' }}</td>
                        <td class="px-4 py-3">{{ $t->numero }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" icon="lock-closed">
                                {{ $t->tipo === 'REQ' ? 'Requerimiento' : 'Solicitud de Pago' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3">{{ $t->fecha->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $t->creador->name }}</td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" :color="$this->estadoColor($t->estado)">
                                {{ $t->estado }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <flux:button size="sm" icon="eye" :href="route('tramites.show', $t)" wire:navigate>Ver</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-zinc-500">
                            No se encontraron trámites.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $tramites->links() }}
    </div>
</div>