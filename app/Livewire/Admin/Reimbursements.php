<?php

namespace App\Livewire\Admin;

use App\Models\History;
use App\Models\Reembolso;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class Reimbursements extends Component
{
    use WithFileUploads;

    public string $tipo = 'Reembolso';
    public string $numero = '';
    public string $fecha = '';
    public string $concepto = '';
    public string $monto = '';
    public string $moneda = 'PEN';
    public string $observaciones = '';
    public array $archivos = [];
    public $evidenciaAtencion;

    public function mount(): void
    {
        abort_unless(auth()->user()->obra_activa_id, 403);
        $this->fecha = now()->format('Y-m-d');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Administración', 'Logística', 'Tesorería', 'Contabilidad', 'Sistemas']), 403);
        $this->validate([
            'tipo' => 'required|in:Reembolso,Rendición', 'numero' => 'nullable|string|max:50', 'fecha' => 'required|date',
            'concepto' => 'required|string', 'monto' => 'required|numeric|min:0.01', 'moneda' => 'required|string|max:10',
            'archivos' => 'array|max:10', 'archivos.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $obraId = auth()->user()->obra_activa_id;
        $numero = $this->numero ?: 'RR-' . substr($this->fecha, 0, 4) . '-' . str_pad((string) (Reembolso::where('obra_id', $obraId)->count() + 1), 3, '0', STR_PAD_LEFT);

        if (Reembolso::where('obra_id', $obraId)->where('numero', $numero)->exists()) {
            $this->addError('numero', "Ya existe un reembolso/rendición con el número {$numero} en esta obra.");

            return;
        }

        DB::transaction(function () use ($obraId, $numero): void {
            $reembolso = Reembolso::create(['obra_id' => $obraId, 'tipo' => $this->tipo, 'numero' => $numero, 'fecha' => $this->fecha, 'solicitante_id' => auth()->id(), 'concepto' => $this->concepto, 'monto' => $this->monto, 'moneda' => $this->moneda, 'observaciones' => $this->observaciones ?: null, 'estado' => 'Pendiente']);
            foreach ($this->archivos as $archivo) {
                $reembolso->adjuntos()->create(['nombre_original' => $archivo->getClientOriginalName(), 'nombre_archivo' => $archivo->store('reembolsos', 'public')]);
            }
        });

        $this->reset(['numero', 'concepto', 'monto', 'observaciones', 'archivos']);
        $this->fecha = now()->format('Y-m-d');
        session()->flash('status', 'Rendición registrada correctamente.');
    }

    public function authorizeReimbursement(int $id): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Administración', 'Gerencia General', 'Sistemas']), 403);
        $reembolso = $this->accessible()->findOrFail($id);
        abort_unless($reembolso->estado === 'Pendiente', 400);
        $reembolso->update(['estado' => 'Autorizado', 'autorizado_por' => auth()->id(), 'fecha_autorizacion' => now()]);
    }

    public function attend(int $id): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Tesorería', 'Sistemas']), 403);
        $reembolso = $this->accessible()->findOrFail($id);
        abort_unless($reembolso->estado === 'Autorizado', 400);
        $this->validate(['evidenciaAtencion' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240']);
        DB::transaction(function () use ($reembolso): void {
            $reembolso->adjuntos()->create([
                'tipo' => 'Evidencia de atención',
                'nombre_original' => $this->evidenciaAtencion->getClientOriginalName(),
                'nombre_archivo' => $this->evidenciaAtencion->store('reembolsos', 'public'),
            ]);
            $reembolso->update(['estado' => 'Atendido', 'atendido_por' => auth()->id(), 'fecha_atencion' => now()]);
        });
        $this->evidenciaAtencion = null;
    }

    protected function accessible()
    {
        return Reembolso::where('obra_id', auth()->user()->obra_activa_id);
    }

    public function render()
    {
        return view('livewire.admin.reimbursements', ['reembolsos' => $this->accessible()->with(['solicitante', 'autorizador', 'atendiente', 'adjuntos'])->latest()->get()]);
    }
}