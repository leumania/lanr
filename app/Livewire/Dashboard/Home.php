<?php

namespace App\Livewire\Dashboard;

use App\Models\Approval;
use App\Models\SolicitudTesoreria;
use App\Models\Tramite;
use Livewire\Component;

class Home extends Component
{
    public function render()
    {
        $user = auth()->user();
        $obraId = $user->obra_activa_id;

        $tramites = fn () => Tramite::where('obra_id', $obraId);

        $activos = $tramites()->where('estado', '!=', 'Cerrado')->count();

        $porAprobar = Approval::where('usuario_id', $user->id)
            ->where('aprobado', false)
            ->whereHas('tramite', fn ($q) => $q->where('obra_id', $obraId))
            ->count();

        [$tercerLabel, $tercerValor] = match (true) {
            $user->hasRole('Logística') => [
                'En gestión logística',
                $tramites()->where('tipo', 'REQ')
                    ->whereIn('estado', ['Recibido por Logística', 'Cotizaciones en gestión', 'En gestión de compra'])
                    ->count(),
            ],
            $user->hasRole('Tesorería') => [
                'Pagos REQ pendientes',
                SolicitudTesoreria::where('estado', 'Pendiente')
                    ->whereHas('tramite', fn ($q) => $q->where('obra_id', $obraId))
                    ->count(),
            ],
            $user->hasRole('Administración') => [
                'SP por revisar',
                $tramites()->where('tipo', 'SP')->where('estado', 'Pendiente asignación de pago')->count(),
            ],
            default => [
                'En oficina / logística',
                $tramites()->where('tipo', 'REQ')
                    ->whereIn('estado', ['Aprobado', 'Recibido por Logística', 'Cotizaciones en gestión', 'En gestión de compra'])
                    ->count(),
            ],
        };

        [$cuartoLabel, $cuartoValor] = match (true) {
            $user->hasRole('Logística') => [
                'Pendientes de envío',
                $tramites()->where('tipo', 'REQ')->where('estado', 'En gestión de compra')
                    ->whereHas('gestionLogistica', fn ($q) => $q->whereIn('estado_pago', ['Pagado por Logística', 'Pagado por Tesorería']))
                    ->count(),
            ],
            $user->hasRole('Tesorería') => [
                'SP asignadas',
                $tramites()->where('tipo', 'SP')->where('estado', 'Asignada a Tesorería')->count(),
            ],
            $user->hasRole('Gerencia General') => [
                'Conformidades pendientes',
                $tramites()->where('tipo', 'SP')->where('estado', 'Pagada pendiente conformidad GG')->count(),
            ],
            default => [
                'Por recibir en obra',
                $tramites()->where('tipo', 'REQ')->where('estado', 'Enviado a obra')->count(),
            ],
        };

        $pendientesAprobacion = Approval::with(['tramite.creador'])
            ->where('usuario_id', $user->id)
            ->where('aprobado', false)
            ->whereHas('tramite', fn ($q) => $q->where('obra_id', $obraId))
            ->get()
            ->sortByDesc(fn ($approval) => $approval->tramite->id)
            ->values();

        return view('livewire.dashboard.home', [
            'activos' => $activos,
            'porAprobar' => $porAprobar,
            'tercerLabel' => $tercerLabel,
            'tercerValor' => $tercerValor,
            'cuartoLabel' => $cuartoLabel,
            'cuartoValor' => $cuartoValor,
            'pendientesAprobacion' => $pendientesAprobacion,
        ]);
    }
}
