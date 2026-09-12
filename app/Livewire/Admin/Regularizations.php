<?php

namespace App\Livewire\Admin;

use App\Models\Regularizacion;
use Livewire\Component;

class Regularizations extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Logística', 'Sistemas']), 403);
    }

    public function render()
    {
        $base = Regularizacion::with(['tramite.creador', 'detalles'])
            ->where('responsable_id', auth()->id());

        return view('livewire.admin.regularizations', [
            'pendientes' => (clone $base)->where('estado', 'Pendiente')->latest('fecha_creacion')->get(),
            'completadas' => (clone $base)->where('estado', '!=', 'Pendiente')->latest('fecha_regularizacion')->take(15)->get(),
        ]);
    }
}
