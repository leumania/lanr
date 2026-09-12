<?php

namespace App\Livewire\Admin;

use App\Models\GestionLogistica;
use App\Models\History;
use App\Models\Reembolso;
use App\Models\SolicitudTesoreria;
use App\Models\Tramite;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class Treasury extends Component
{
    use WithFileUploads;

    public string $medio = 'Transferencia';
    public string $medioOtro = '';
    public string $banco = '';
    public string $operacion = '';
    public string $fecha = '';
    public string $monto = '';
    public $comprobante;
    public $evidenciaReembolso;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['Tesorería', 'Sistemas']), 403);
        abort_unless(auth()->user()->obra_activa_id !== null, 403);
        $this->fecha = now()->format('Y-m-d');
    }

    public function pagarSolicitud(int $solicitudId): void
    {
        abort_unless(auth()->user()->hasRole('Tesorería'), 403);

        $solicitud = SolicitudTesoreria::where('tramite_id', function ($query) {
            $query->select('id')->from('tramites')->where('obra_id', auth()->user()->obra_activa_id);
        })->findOrFail($solicitudId);
        abort_unless($solicitud->estado === 'Pendiente', 400);

        $this->validate([
            'medio' => 'required|string|max:50',
            'medioOtro' => $this->medio === 'Otro' ? 'required|string|max:50' : 'nullable|string|max:50',
            'banco' => 'nullable|string|max:100',
            'operacion' => 'nullable|string|max:100',
            'fecha' => 'required|date',
            'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        abort_unless(abs((float) $this->monto - (float) $solicitud->monto) < 0.01, 422, 'El monto debe coincidir con la solicitud.');

        DB::transaction(function () use ($solicitud): void {
            $solicitud->pagos()->create([
                'nombre_original' => $this->comprobante->getClientOriginalName(),
                'nombre_archivo' => $this->comprobante->store('pagos-tesoreria', 'public'),
                'medio_pago' => $this->medio === 'Otro' ? $this->medioOtro : $this->medio,
                'banco' => $this->banco ?: null,
                'monto' => $solicitud->monto,
                'nro_operacion' => $this->operacion ?: null,
            ]);
            $solicitud->update(['estado' => 'Atendida', 'fecha_atencion' => now()]);

            if (in_array($solicitud->origen, ['REQ-Compra', 'REQ-Cotizacion'], true)) {
                $tramite = Tramite::findOrFail($solicitud->tramite_id);
                $pendientes = $tramite->solicitudesTesoreria()
                    ->whereIn('origen', ['REQ-Compra', 'REQ-Cotizacion'])
                    ->where('estado', 'Pendiente')
                    ->exists();

                if (! $pendientes) {
                    GestionLogistica::updateOrCreate(
                        ['tramite_id' => $solicitud->tramite_id],
                        ['estado_pago' => 'Pagado por Tesorería', 'forma_pago' => 'Tesorería']
                    );
                }
            }

            History::create([
                'tramite_id' => $solicitud->tramite_id,
                'usuario_id' => auth()->id(),
                'accion' => 'Pago registrado por Tesorería desde la bandeja central',
            ]);
        });

        $this->resetPaymentForm();
        session()->flash('status', 'Pago registrado correctamente.');
    }

    public function atenderReembolso(int $reembolsoId): void
    {
        abort_unless(auth()->user()->hasRole('Tesorería'), 403);
        $reembolso = Reembolso::where('obra_id', auth()->user()->obra_activa_id)->findOrFail($reembolsoId);
        abort_unless($reembolso->estado === 'Autorizado', 400);

        $this->validate(['evidenciaReembolso' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240']);

        DB::transaction(function () use ($reembolso): void {
            $reembolso->adjuntos()->create([
                'tipo' => 'Evidencia de atención',
                'nombre_original' => $this->evidenciaReembolso->getClientOriginalName(),
                'nombre_archivo' => $this->evidenciaReembolso->store('reembolsos', 'public'),
            ]);
            $reembolso->update(['estado' => 'Atendido', 'atendido_por' => auth()->id(), 'fecha_atencion' => now()]);
        });

        $this->evidenciaReembolso = null;
        session()->flash('status', 'Reembolso atendido con evidencia.');
    }

    protected function resetPaymentForm(): void
    {
        $this->reset(['medioOtro', 'banco', 'operacion', 'monto', 'comprobante']);
        $this->fecha = now()->format('Y-m-d');
    }

    public function render()
    {
        $obraId = auth()->user()->obra_activa_id;

        $solicitudes = SolicitudTesoreria::whereHas('tramite', fn ($query) => $query->where('obra_id', $obraId))
            ->whereIn('origen', ['REQ-Compra', 'REQ-Cotizacion', 'REQ-Reembolso'])
            ->with('tramite', 'solicitante')
            ->orderByRaw("CASE WHEN estado = 'Pendiente' THEN 0 ELSE 1 END")
            ->latest()
            ->get();

        $reembolsos = Reembolso::where('obra_id', $obraId)->where('estado', 'Autorizado')->with('solicitante')->latest()->get();

        return view('livewire.admin.treasury', [
            'solicitudes' => $solicitudes,
            'reembolsos' => $reembolsos,
            'pendientesCount' => $solicitudes->where('estado', 'Pendiente')->count(),
            'pendientesMonto' => $solicitudes->where('estado', 'Pendiente')->sum('monto'),
            'reembolsosMonto' => $reembolsos->sum('monto'),
        ]);
    }
}