<?php

namespace App\Livewire\Admin;

use App\Models\MonedaCatalogo;
use App\Models\Orden;
use Livewire\Component;

class CurrencyManagement extends Component
{
    public string $codigo = '';

    public string $nombre = '';

    public string $simbolo = '';

    public ?int $editingId = null;

    public bool $mostrarFormulario = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('Sistemas'), 403);
    }

    public function nuevo(): void
    {
        $this->reset(['codigo', 'nombre', 'simbolo', 'editingId']);
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $moneda = MonedaCatalogo::findOrFail($id);

        $this->editingId = $moneda->id;
        $this->codigo = $moneda->codigo;
        $this->nombre = $moneda->nombre;
        $this->simbolo = $moneda->simbolo;
        $this->mostrarFormulario = true;
    }

    public function cancelar(): void
    {
        $this->reset(['codigo', 'nombre', 'simbolo', 'editingId', 'mostrarFormulario']);
    }

    public function save(): void
    {
        $this->validate([
            'codigo' => 'required|string|max:10',
            'nombre' => 'required|string|max:50',
            'simbolo' => 'required|string|max:10',
        ]);

        $codigo = strtoupper(trim($this->codigo));

        $duplicado = MonedaCatalogo::whereRaw('UPPER(codigo) = ?', [$codigo])
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicado) {
            $this->addError('codigo', 'Ya existe una moneda con este código.');

            return;
        }

        $moneda = $this->editingId ? MonedaCatalogo::findOrFail($this->editingId) : new MonedaCatalogo;
        $moneda->fill([
            'codigo' => $codigo,
            'nombre' => trim($this->nombre),
            'simbolo' => trim($this->simbolo),
            'active' => $moneda->active ?? true,
        ])->save();

        $this->reset(['codigo', 'nombre', 'simbolo', 'editingId', 'mostrarFormulario']);
        session()->flash('status', 'Moneda guardada correctamente.');
    }

    public function toggle(int $id): void
    {
        $moneda = MonedaCatalogo::findOrFail($id);
        $moneda->update(['active' => ! $moneda->active]);
    }

    public function delete(int $id): void
    {
        $moneda = MonedaCatalogo::findOrFail($id);

        $enUso = Orden::where('moneda', $moneda->codigo)->exists();

        if ($enUso) {
            $moneda->update(['active' => false]);
            session()->flash('status', 'La moneda ya fue usada en órdenes, por lo que se desactivó para conservar el historial en vez de eliminarla.');

            return;
        }

        $moneda->delete();
        session()->flash('status', 'Moneda eliminada correctamente.');
    }

    public function render()
    {
        return view('livewire.admin.currency-management', [
            'monedas' => MonedaCatalogo::orderBy('codigo')->get(),
        ]);
    }
}
