<?php

namespace App\Livewire\Admin;

use App\Models\UnidadCatalogo;
use Livewire\Component;

class UnitManagement extends Component
{
    public string $abreviatura = '';

    public string $uso = 'REQ';

    public ?int $editingId = null;

    public bool $mostrarFormulario = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('Sistemas'), 403);
    }

    public function nuevo(): void
    {
        $this->reset(['abreviatura', 'editingId']);
        $this->uso = 'REQ';
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $unidad = UnidadCatalogo::findOrFail($id);

        $this->editingId = $unidad->id;
        $this->abreviatura = $unidad->abreviatura;
        $this->uso = $unidad->uso;
        $this->mostrarFormulario = true;
    }

    public function cancelar(): void
    {
        $this->reset(['abreviatura', 'uso', 'editingId', 'mostrarFormulario']);
    }

    public function save(): void
    {
        $this->validate([
            'abreviatura' => 'required|string|max:20',
            'uso' => 'required|in:REQ,SP,AMBOS',
        ]);

        $abreviatura = strtoupper(trim($this->abreviatura));

        $duplicado = UnidadCatalogo::where('uso', $this->uso)
            ->whereRaw('UPPER(abreviatura) = ?', [$abreviatura])
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicado) {
            $this->addError('abreviatura', 'Ya existe una unidad con esta abreviatura para este uso.');

            return;
        }

        $unidad = $this->editingId ? UnidadCatalogo::findOrFail($this->editingId) : new UnidadCatalogo;
        $unidad->fill(['abreviatura' => $abreviatura, 'uso' => $this->uso, 'active' => $unidad->active ?? true])->save();

        $this->reset(['abreviatura', 'uso', 'editingId', 'mostrarFormulario']);
        $this->uso = 'REQ';
        session()->flash('status', 'Unidad guardada correctamente.');
    }

    public function toggle(int $id): void
    {
        $unidad = UnidadCatalogo::findOrFail($id);
        $unidad->update(['active' => ! $unidad->active]);
    }

    public function delete(int $id): void
    {
        UnidadCatalogo::findOrFail($id)->delete();
        session()->flash('status', 'Unidad eliminada correctamente.');
    }

    public function render()
    {
        return view('livewire.admin.unit-management', [
            'unidades' => UnidadCatalogo::orderBy('uso')->orderBy('abreviatura')->get(),
        ]);
    }
}
