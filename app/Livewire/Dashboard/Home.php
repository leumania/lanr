<?php

namespace App\Livewire\Dashboard;

use App\Models\Approval;
use App\Models\Reembolso;
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
                $tramites()->where('tipo', 'SP')->where('estado', 'Pendiente revisión de Administración')->count(),
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

        $pendientesLogistica = collect();

        if ($user->hasRole('Logística')) {
            $pendientesLogistica = $tramites()
                ->with(['creador', 'gestionLogistica', 'cotizaciones'])
                ->where('tipo', 'REQ')
                ->whereIn('estado', ['Aprobado', 'Recibido por Logística', 'Cotizaciones en gestión', 'En gestión de compra'])
                ->latest('id')
                ->get()
                ->map(function (Tramite $t) {
                    $g = $t->gestionLogistica;
                    $enviadaAAdministracion = $t->cotizaciones->contains(fn ($cotizacion) => $cotizacion->estado === 'Enviada a Administración');

                    // Réplica de los 6 estados granulares de Logística del prototipo V11,
                    // derivados de combinaciones de campos existentes (sin agregar campos nuevos).
                    $t->pendiente_de = match (true) {
                        $t->estado === 'Aprobado' => 'Recepcionar',
                        $t->estado === 'Recibido por Logística' => 'Cotizaciones',
                        $t->estado === 'Cotizaciones en gestión' && $enviadaAAdministracion => 'Autorización de Sara',
                        $t->estado === 'Cotizaciones en gestión' => 'Cotizaciones',
                        $t->estado === 'En gestión de compra' && blank($g?->estado_pago) => 'Pago por proveedor',
                        $t->estado === 'En gestión de compra' && $g?->comprobante_pendiente => 'Comprobantes de compra',
                        $t->estado === 'En gestión de compra' && in_array($g?->estado_pago, ['Pagado por Logística', 'Pagado por Tesorería']) => 'Despacho a obra',
                        default => 'Revisar',
                    };

                    return $t;
                });
        }

        $spPendientesAsignacion = collect();
        $spPendientesConformidad = collect();

        if ($user->hasRole('Gerencia General')) {
            $spPendientesAsignacion = $tramites()
                ->with('creador')
                ->where('tipo', 'SP')
                ->where('estado', 'Pendiente revisión de Administración')
                ->latest('id')
                ->get();

            $spPendientesConformidad = $tramites()
                ->with(['creador', 'gestionSp'])
                ->where('tipo', 'SP')
                ->where('estado', 'Pagada pendiente conformidad GG')
                ->whereHas('gestionSp', fn ($q) => $q->where('conformidad_gg', false))
                ->latest('id')
                ->get();
        }

        $spTesoreria = collect();
        $reembolsosPendientes = collect();

        if ($user->hasRole('Tesorería')) {
            $spTesoreria = $tramites()
                ->with(['creador', 'gestionSp'])
                ->where('tipo', 'SP')
                ->where('estado', 'Asignada a Tesorería')
                ->latest('id')
                ->get();

            $reembolsosPendientes = Reembolso::where('obra_id', $obraId)
                ->where('estado', 'Autorizado')
                ->with('solicitante')
                ->latest()
                ->get();
        }

        return view('livewire.dashboard.home', [
            'activos' => $activos,
            'porAprobar' => $porAprobar,
            'tercerLabel' => $tercerLabel,
            'tercerValor' => $tercerValor,
            'cuartoLabel' => $cuartoLabel,
            'cuartoValor' => $cuartoValor,
            'pendientesAprobacion' => $pendientesAprobacion,
            'pendientesLogistica' => $pendientesLogistica,
            'spPendientesAsignacion' => $spPendientesAsignacion,
            'spPendientesConformidad' => $spPendientesConformidad,
            'spTesoreria' => $spTesoreria,
            'reembolsosPendientes' => $reembolsosPendientes,
        ]);
    }
}
