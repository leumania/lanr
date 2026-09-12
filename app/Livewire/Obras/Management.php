<?php

namespace App\Livewire\Obras;

use App\Models\Obra;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Management extends Component
{
    public string $nombre = '';
    public string $codigo = '';
    public string $proyecto = '';
    public string $lugar = '';
    public string $descripcion = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Gerencia General', 'Sistemas', 'Gerencia de Obra']), 403);
    }

    public function save(): void
    {
        $this->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => ['required', 'string', 'max:50', Rule::unique('obras', 'codigo')],
            'proyecto' => 'nullable|string',
            'lugar' => 'nullable|string',
            'descripcion' => 'nullable|string',
        ]);

        Obra::create([
            'nombre' => $this->nombre,
            'codigo' => strtoupper($this->codigo),
            'proyecto' => $this->proyecto ?: $this->nombre,
            'lugar' => $this->lugar ?: $this->nombre,
            'descripcion' => $this->descripcion,
            'activa' => true,
        ]);

        $this->reset(['nombre', 'codigo', 'proyecto', 'lugar', 'descripcion']);
        session()->flash('status', 'Obra registrada correctamente.');
    }

    public function toggle(int $obraId): void
    {
        $obra = Obra::findOrFail($obraId);
        $obra->update(['activa' => ! $obra->activa]);
        session()->flash('status', 'Estado de la obra actualizado.');
    }

    public function render()
    {
        return view('livewire.obras.management', ['obras' => Obra::orderBy('nombre')->get()]);
    }
}
