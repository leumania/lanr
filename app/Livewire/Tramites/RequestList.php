<?php

namespace App\Livewire\Tramites;

use App\Models\Tramite;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class RequestList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $tipo = '';

    public string $flujo = 'pendientes';

    protected array $flujos = ['pendientes', 'aprobar', 'proceso', 'finalizados', 'todos'];

    protected array $notas = [
        'pendientes' => 'Aquí aparecen los trámites que creó y todavía no ha enviado.',
        'aprobar' => 'Aquí aparecen los trámites que todavía requieren completar los V°B° de Obra.',
        'proceso' => 'Aquí aparecen los trámites que completaron los V°B° de Obra y están siendo gestionados en oficina.',
        'finalizados' => 'Aquí aparecen los trámites cuyo proceso ya finalizó.',
        'todos' => 'Aquí aparecen todos los trámites que tiene permiso para visualizar.',
    ];

    public function mount(): void
    {
        $conteos = $this->conteos();

        if ($conteos['pendientes'] === 0) {
            $this->flujo = collect(['aprobar', 'proceso', 'finalizados', 'todos'])
                ->first(fn ($flujo) => $conteos[$flujo] > 0) ?? 'todos';
        }
    }

    public function setFlujo(string $flujo): void
    {
        if (in_array($flujo, $this->flujos, true)) {
            $this->flujo = $flujo;
            $this->resetPage();
        }
    }

    public function updatingTipo(): void
    {
        $this->resetPage();
    }

    public function buscar(): void
    {
        $this->resetPage();
    }

    public function limpiar(): void
    {
        $this->reset('search', 'tipo');
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        return Tramite::query()
            ->where('obra_id', auth()->user()->obra_activa_id)
            ->where(function ($query) {
                $query->where('creador_id', auth()->id())
                    ->orWhere('estado', '!=', 'Pendiente de mi revisión');
            });
    }

    protected function aplicarFlujo(Builder $query, string $flujo): Builder
    {
        return match ($flujo) {
            'pendientes' => $query->where('estado', 'Pendiente de mi revisión'),
            'aprobar' => $query->where('estado', 'Pendiente de aprobación'),
            'finalizados' => $query->where('estado', 'Cerrado'),
            'proceso' => $query->whereNotIn('estado', ['Pendiente de mi revisión', 'Pendiente de aprobación', 'Cerrado']),
            default => $query,
        };
    }

    protected function conteos(): array
    {
        return collect($this->flujos)
            ->mapWithKeys(fn ($flujo) => [$flujo => $this->aplicarFlujo($this->baseQuery(), $flujo)->count()])
            ->all();
    }

    public function render()
    {
        $tramites = $this->aplicarFlujo($this->baseQuery(), $this->flujo)
            ->when($this->tipo, fn ($q) => $q->where('tipo', $this->tipo))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('tracking', 'like', "%{$this->search}%")
                    ->orWhere('numero', 'like', "%{$this->search}%")
                    ->orWhere('formato', 'like', "%{$this->search}%");
            }))
            ->with('creador')
            ->latest()
            ->paginate(10);

        return view('livewire.tramites.request-list', [
            'tramites' => $tramites,
            'conteos' => $this->conteos(),
            'nota' => $this->notas[$this->flujo],
        ]);
    }
}
