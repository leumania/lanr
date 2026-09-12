<?php

namespace App\Livewire\Obras;

use App\Models\Obra;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Selector extends Component
{
    public ?int $obraId = null;

    public function mount(): void
    {
        $this->obraId = auth()->user()->obra_activa_id;
    }

    public function updatedObraId(?int $obraId): void
    {
        $obra = auth()->user()->accessibleObras()->whereKey($obraId)->firstOrFail();

        auth()->user()->update(['obra_activa_id' => $obra->id]);
        session(['obra_activa_id' => $obra->id]);
        $this->redirect(request()->header('Referer') ?: route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.obras.selector', [
            'obras' => auth()->user()->accessibleObras()->orderBy('nombre')->get(),
        ]);
    }
}
