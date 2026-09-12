<?php

namespace App\Livewire\Tramites;

use App\Models\SpComprobante;
use App\Models\SpCuenta;
use App\Models\SpModalidad;
use App\Models\Tramite;
use Livewire\Component;

class SpAdvanced extends Component
{
    public Tramite $tramite;
    public string $modalidad = '';
    public string $montoModalidad = '';
    public string $banco = '';
    public string $cuentaCci = '';
    public string $tipoComprobante = '';
    public string $numeroComprobante = '';
    public string $montoComprobante = '';

    public function mount(Tramite $tramite): void
    {
        abort_unless($tramite->tipo === 'SP', 404);
        abort_unless(auth()->user()->can('view', $tramite), 404);
        abort_unless(auth()->user()->hasAnyRole(['Administración', 'Tesorería', 'Gerencia General', 'Sistemas']), 403);
        $this->tramite = $tramite->load(['spModalidades', 'spCuentas', 'spComprobantes']);
    }

    public function addModalidad(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Administración', 'Tesorería', 'Gerencia General', 'Sistemas']), 403);
        $this->validate(['modalidad' => 'required|string|max:100', 'montoModalidad' => 'required|numeric|min:0.01']);
        $this->tramite->spModalidades()->create(['nombre' => $this->modalidad, 'monto' => $this->montoModalidad]);
        $this->reset(['modalidad', 'montoModalidad']);
        $this->tramite->load('spModalidades');
    }

    public function addCuenta(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Administración', 'Tesorería', 'Gerencia General', 'Sistemas']), 403);
        $this->validate(['banco' => 'required|string|max:100', 'cuentaCci' => 'required|string|max:100']);
        $this->tramite->spCuentas()->create(['banco' => $this->banco, 'cuenta_cci' => $this->cuentaCci]);
        $this->reset(['banco', 'cuentaCci']);
        $this->tramite->load('spCuentas');
    }

    public function addComprobante(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Administración', 'Tesorería', 'Gerencia General', 'Sistemas']), 403);
        $this->validate(['tipoComprobante' => 'required|string|max:50', 'numeroComprobante' => 'required|string|max:80', 'montoComprobante' => 'required|numeric|min:0.01']);
        $this->tramite->spComprobantes()->create(['tipo' => $this->tipoComprobante, 'numero' => $this->numeroComprobante, 'monto' => $this->montoComprobante]);
        $this->reset(['tipoComprobante', 'numeroComprobante', 'montoComprobante']);
        $this->tramite->load('spComprobantes');
    }

    public function render()
    {
        return view('livewire.tramites.sp-advanced');
    }
}