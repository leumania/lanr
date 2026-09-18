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
            // Esta bandeja está pensada específicamente para regularización de compra
            // (comprobantes/detalle de compra), tal como en V11. Otros tipos de
            // regularización (p.ej. guía de remisión) se listan aparte, sin las
            // columnas de comprobantes/monto que no les aplican.
            'pendientes' => (clone $base)->where('estado', 'Pendiente')->where('tipo', 'Regularización de compra')->latest('fecha_creacion')->get(),
            'completadas' => (clone $base)->where('estado', '!=', 'Pendiente')->where('tipo', 'Regularización de compra')->latest('fecha_regularizacion')->take(15)->get(),
            'otrasPendientes' => (clone $base)->where('estado', 'Pendiente')->where('tipo', '!=', 'Regularización de compra')->latest('fecha_creacion')->get(),
        ]);
    }
}
