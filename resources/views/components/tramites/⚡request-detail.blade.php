<?php

use App\Models\Approval;
use App\Models\History;
use App\Models\Notificacion;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use App\Models\GestionLogistica;
use App\Models\Regularizacion;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;
    
    #[Locked]
    public Tramite $tramite;
    public array $detalle_compra = [];

    public function mount(Tramite $tramite): void
    {
        $this->tramite = $tramite;
        $this->tramite->loadMissing('items.imagenes');
        $this->detalle_compra = $this->tramite->items
            ->filter(fn ($item) => (float) $item->comprar > 0)
            ->mapWithKeys(fn ($item) => [$item->id => ['cantidad' => 0, 'observacion' => '']])
            ->all();
    }

    protected function notificarRoles(array $roles, string $titulo, string $mensaje, string $tipo = 'informativa'): void
    {
        User::role($roles)
            ->activeAssignedToObra($this->tramite->obra_id)
            ->get()
            ->each(fn (User $destinatario) => Notificacion::create([
                'usuario_id' => $destinatario->id,
                'tramite_id' => $this->tramite->id,
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'tipo' => $tipo,
            ]));
    }

    public function creatorApprove(): void
    {
        abort_unless($this->tramite->creador_id === auth()->id(), 403);
        abort_unless($this->tramite->estado === 'Pendiente de mi revisión', 400);

        DB::transaction(function (): void {
            $this->tramite->update(['estado' => 'Pendiente de aprobación']);
            History::create([
                'tramite_id' => $this->tramite->id,
                'usuario_id' => auth()->id(),
                'accion' => 'Revisión inicial confirmada por el creador',
            ]);
        });

        session()->flash('status', 'Trámite enviado a aprobación.');
        $this->refrescar();
    }

    public function deleteRequest(): void
    {
        abort_unless($this->tramite->creador_id === auth()->id(), 403);
        abort_unless($this->tramite->estado === 'Pendiente de mi revisión', 400);

        $tracking = $this->tramite->tracking;
        DB::transaction(function (): void {
            Tramite::whereKey($this->tramite->id)->delete();
        });
        session()->flash('status', "Trámite {$tracking} eliminado.");
        $this->redirectRoute('tramites.index', navigate: true);
    }

    public function getPuedeAprobarProperty(): ?Approval
    {
        $user = auth()->user();

        return $this->tramite->approvals
            ->where('aprobado', false)
            ->first(fn ($a) => $a->usuario_id === $user->id || $user->hasRole('Sistemas'));
    }

    public function aprobar(int $approvalId): void
    {
        $approval = Approval::findOrFail($approvalId);
        $user = auth()->user();

        abort_unless(
            $approval->usuario_id === $user->id || $user->hasRole('Sistemas'),
            403
        );

        DB::transaction(function () use ($approval, $user) {
            $approval->update([
                'aprobado' => true,
                'fecha_aprobacion' => now(),
            ]);

            History::create([
                'tramite_id' => $approval->tramite_id,
                'usuario_id' => $user->id,
                'accion' => "Visto bueno: {$approval->rol}",
            ]);

            $pendientes = Approval::where('tramite_id', $approval->tramite_id)
                ->where('aprobado', false)
                ->count();

            if ($pendientes === 0) {
                $tramite = Tramite::find($approval->tramite_id);

                if ($tramite->tipo === 'REQ') {
                    $tramite->update(['estado' => 'Aprobado']);

                    $roles = ['Logística', 'Administración', 'Gerencia General', 'Tesorería'];

                    foreach (User::role($roles)->get() as $destinatario) {
                        $esLogistica = $destinatario->hasRole('Logística');

                        Notificacion::create([
                            'usuario_id' => $destinatario->id,
                            'tramite_id' => $tramite->id,
                            'titulo' => $esLogistica ? 'Nuevo requerimiento para atención' : 'Requerimiento aprobado',
                            'mensaje' => $esLogistica
                                ? "El requerimiento {$tramite->tracking} fue aprobado y está listo para ser atendido por Logística."
                                : "El requerimiento {$tramite->tracking} completó sus V°B° y pasó a gestión de Logística.",
                            'tipo' => $esLogistica ? 'accion' : 'informativa',
                        ]);
                    }
                } elseif ($tramite->tipo === 'SP') {
                    $tramite->update(['estado' => 'Pendiente asignación de pago']);

                    $gerenteGeneral = User::role('Gerencia General')->first();

                    if ($gerenteGeneral) {
                        Notificacion::create([
                            'usuario_id' => $gerenteGeneral->id,
                            'tramite_id' => $tramite->id,
                            'titulo' => 'Solicitud lista para asignar pago',
                            'mensaje' => "La solicitud {$tramite->tracking} completó los V°B° de Administración y Logística. Debes revisar el expediente y decidir quién realizará el pago.",
                            'tipo' => 'accion',
                        ]);
                    }
                }
            }
        });

        $this->tramite->refresh();
        $this->tramite->load(['approvals.usuario', 'history.usuario']);

        session()->flash('status', 'Visto bueno registrado correctamente.');
    }
    
        public function recibirLogistica(): void
    {
        $user = auth()->user();
        abort_unless($user->hasAnyRole(['Logística', 'Sistemas']), 403);
        abort_unless($this->tramite->tipo === 'REQ' && $this->tramite->estado === 'Aprobado', 400);

        DB::transaction(function () use ($user) {
            \App\Models\GestionLogistica::updateOrCreate(
                ['tramite_id' => $this->tramite->id],
                ['recibido_fecha' => now()]
            );

            $this->tramite->update(['estado' => 'Recibido por Logística']);

            History::create([
                'tramite_id' => $this->tramite->id,
                'usuario_id' => $user->id,
                'accion' => 'Requerimiento recibido por Logística',
            ]);
        });

        $this->refrescar();
    }

    public array $compra = [
        'fecha_compra' => '',
        'tipo_comprobante' => 'Factura',
        'nro_comprobante' => '',
        'monto' => 0,
        'pendiente_regularizacion' => false,
    ];
    public array $archivos_compra = [];

    public function registrarCompra(): void
    {
        $user = auth()->user();
        abort_unless($user->hasRole('Logística'), 403);

        $this->validate([
            'compra.fecha_compra' => 'required|date',
            'compra.tipo_comprobante' => 'required|in:Factura,Boleta,Otro',
            'compra.monto' => 'required|numeric|min:0.01',
            'detalle_compra' => 'array',
            'detalle_compra.*.cantidad' => 'numeric|min:0',
        ], [
            'compra.fecha_compra.required' => 'El campo fecha de compra es obligatorio.',
            'compra.fecha_compra.date' => 'El campo fecha de compra debe ser una fecha válida.',
            'compra.tipo_comprobante.required' => 'El campo tipo de comprobante es obligatorio.',
            'compra.tipo_comprobante.in' => 'Selecciona un tipo de comprobante válido.',
            'compra.monto.required' => 'El campo monto es obligatorio.',
            'compra.monto.numeric' => 'El campo monto debe ser un número.',
            'compra.monto.min' => 'El monto debe ser mayor a 0.',
            'detalle_compra.*.cantidad' => 'La cantidad comprada debe ser válida.',
            'archivos_compra' => 'array|max:10',
            'archivos_compra.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,xml|max:10240',
        ]);

        foreach ($this->tramite->items as $item) {
            $cantidad = (float) ($this->detalle_compra[$item->id]['cantidad'] ?? 0);
            if ($cantidad > (float) $item->comprar) {
                $this->addError("detalle_compra.{$item->id}.cantidad", 'La cantidad supera lo pendiente de compra.');
                return;
            }
        }

        DB::transaction(function () use ($user) {
            \App\Models\GestionLogistica::updateOrCreate(
                ['tramite_id' => $this->tramite->id],
                [
                    'fecha_compra' => $this->compra['fecha_compra'],
                    'tipo_comprobante' => $this->compra['tipo_comprobante'],
                    'nro_comprobante' => $this->compra['nro_comprobante'],
                    'monto' => $this->compra['monto'],
                    'comprobante_pendiente' => $this->compra['pendiente_regularizacion'],
                    'estado_pago' => null,
                    'forma_pago' => null,
                ]
            );

            if ($this->compra['pendiente_regularizacion']) {
                \App\Models\Regularizacion::create([
                    'tramite_id' => $this->tramite->id,
                    'responsable_id' => $user->id,
                    'tipo' => 'Regularización de compra',
                    'descripcion' => 'Completar la información y documentación definitiva de la compra.',
                    'estado' => 'Pendiente',
                    'fecha_creacion' => now(),
                ]);
            }

            $primerArchivo = null;
            foreach ($this->archivos_compra as $archivo) {
                $archivoLogistica = \App\Models\ArchivoLogistica::create([
                    'tramite_id' => $this->tramite->id,
                    'tipo' => 'comprobante',
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'nombre_archivo' => $archivo->store('archivos-logistica', 'public'),
                ]);
                $primerArchivo ??= $archivoLogistica;
            }

            $compraDetallada = \App\Models\CompraLogistica::create([
                'tramite_id' => $this->tramite->id,
                'fecha_compra' => $this->compra['fecha_compra'],
                'tipo_comprobante' => $this->compra['tipo_comprobante'],
                'nro_comprobante' => $this->compra['nro_comprobante'],
                'monto' => $this->compra['monto'],
                'nombre_original' => $primerArchivo?->nombre_original,
                'nombre_archivo' => $primerArchivo?->nombre_archivo,
                'creado_por' => $user->id,
            ]);

            foreach ($this->tramite->items as $item) {
                $detalle = $this->detalle_compra[$item->id] ?? null;
                if ($detalle && (float) ($detalle['cantidad'] ?? 0) > 0) {
                    $compraDetallada->detalles()->create([
                        'item_id' => $item->id,
                        'cantidad_comprada' => $detalle['cantidad'],
                        'observacion' => $detalle['observacion'] ?: null,
                    ]);
                }
            }

            $this->tramite->update(['estado' => 'En gestión de compra']);

            $accion = 'Compra registrada por Logística';
            if ($this->compra['pendiente_regularizacion']) {
                $accion .= ' - Pendiente de regularización';
            }

            History::create([
                'tramite_id' => $this->tramite->id,
                'usuario_id' => $user->id,
                'accion' => $accion,
            ]);
        });

        session()->flash('status', 'Compra registrada correctamente.');
        $this->refrescar();
    }

    public $archivo_regularizacion;
    public array $detalle_regularizacion = [];
    public array $comprobantes_definitivos = [];

    public function addDetalleRegularizacion(): void
    {
        $this->detalle_regularizacion[] = ['descripcion' => '', 'cantidad' => 0, 'precio_unitario' => 0];
    }

    public function addComprobanteDefinitivo(): void
    {
        $this->comprobantes_definitivos[] = [
            'fecha_compra' => now()->format('Y-m-d'),
            'tipo_comprobante' => 'Factura',
            'nro_comprobante' => '',
            'monto' => 0,
            'archivo' => null,
        ];
    }

    public function registrarComprobantesDefinitivos(): void
    {
        abort_unless(auth()->user()->hasRole('Logística'), 403);
        abort_unless($this->tramite->gestionLogistica?->estado_pago, 400);

        $this->validate([
            'comprobantes_definitivos' => 'required|array|min:1',
            'comprobantes_definitivos.*.fecha_compra' => 'required|date',
            'comprobantes_definitivos.*.tipo_comprobante' => 'required|in:Factura,Boleta,Otro',
            'comprobantes_definitivos.*.nro_comprobante' => 'required|string|max:100',
            'comprobantes_definitivos.*.monto' => 'required|numeric|min:0.01',
            'comprobantes_definitivos.*.archivo' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,xml|max:10240',
        ]);

        DB::transaction(function (): void {
            foreach ($this->comprobantes_definitivos as $comprobante) {
                $archivo = $comprobante['archivo'];
                $ruta = $archivo->store('archivos-logistica', 'public');

                $compra = \App\Models\CompraLogistica::create([
                    'tramite_id' => $this->tramite->id,
                    'fecha_compra' => $comprobante['fecha_compra'],
                    'tipo_comprobante' => $comprobante['tipo_comprobante'],
                    'nro_comprobante' => $comprobante['nro_comprobante'],
                    'monto' => $comprobante['monto'],
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'nombre_archivo' => $ruta,
                    'creado_por' => auth()->id(),
                ]);

                \App\Models\ArchivoLogistica::create([
                    'tramite_id' => $this->tramite->id,
                    'tipo' => "comprobante_{$compra->id}",
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'nombre_archivo' => $ruta,
                ]);
            }

            $regularizacion = $this->tramite->regularizaciones()->firstOrCreate(
                ['tipo' => 'Regularización de compra', 'estado' => 'Pendiente'],
                [
                    'responsable_id' => auth()->id(),
                    'descripcion' => 'Detallar los materiales comprados y sustentar los comprobantes definitivos.',
                    'fecha_creacion' => now(),
                ]
            );

            $this->tramite->gestionLogistica()->update(['comprobante_pendiente' => true]);
            History::create([
                'tramite_id' => $this->tramite->id,
                'usuario_id' => auth()->id(),
                'accion' => 'Comprobantes definitivos registrados por Logística',
            ]);
        });

        $this->comprobantes_definitivos = [];
        session()->flash('status', 'Comprobantes definitivos registrados.');
        $this->refrescar();
    }

    public function regularizar(int $regularizacionId): void
    {
        $regularizacion = Regularizacion::where('tramite_id', $this->tramite->id)
            ->findOrFail($regularizacionId);

        abort_unless($regularizacion->responsable_id === auth()->id(), 403);
        abort_unless($regularizacion->estado === 'Pendiente', 400);

        $this->validate([
            'archivo_regularizacion' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,xml|max:10240',
            'detalle_regularizacion' => 'array',
            'detalle_regularizacion.*.descripcion' => 'required_with:detalle_regularizacion.*.cantidad|string|max:255',
            'detalle_regularizacion.*.cantidad' => 'required_with:detalle_regularizacion.*.descripcion|numeric|min:0.01',
            'detalle_regularizacion.*.precio_unitario' => 'required_with:detalle_regularizacion.*.descripcion|numeric|min:0',
        ], [
            'archivo_regularizacion.required' => 'Debes adjuntar el documento de regularización.',
            'archivo_regularizacion.mimes' => 'El documento debe ser PDF, imagen o XML.',
            'archivo_regularizacion.max' => 'El documento no debe superar 10 MB.',
        ]);

        $detalles = $this->detalle_regularizacion;
        $detalleTotal = $detalles !== []
            ? collect($detalles)->sum(fn (array $detalle): float => round((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2))
            : (float) $regularizacion->detalles()->sum('precio_total');
        if ($detalleTotal > 0) {
            $compraTotal = (float) \App\Models\CompraLogistica::where('tramite_id', $this->tramite->id)->sum('monto');
            if (abs($detalleTotal - $compraTotal) > 0.01) {
                $this->addError('archivo_regularizacion', 'El detalle de regularización debe cuadrar con los comprobantes de compra.');
                return;
            }
        }

        DB::transaction(function () use ($regularizacion, $detalles) {
            if ($detalles !== []) {
                $regularizacion->detalles()->delete();
                foreach ($detalles as $detalle) {
                    $regularizacion->detalles()->create([
                        'descripcion' => $detalle['descripcion'],
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $detalle['precio_unitario'],
                        'precio_total' => round((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2),
                    ]);
                }
            }

            \App\Models\ArchivoLogistica::create([
                'tramite_id' => $this->tramite->id,
                'tipo' => "regularizacion_{$regularizacion->id}",
                'nombre_original' => $this->archivo_regularizacion->getClientOriginalName(),
                'nombre_archivo' => $this->archivo_regularizacion->store('archivos-logistica', 'public'),
            ]);

            $regularizacion->update([
                'estado' => 'Regularizada',
                'fecha_regularizacion' => now(),
            ]);

            History::create([
                'tramite_id' => $this->tramite->id,
                'usuario_id' => auth()->id(),
                'accion' => "Regularización completada: {$regularizacion->tipo}",
            ]);
        });

        $this->archivo_regularizacion = null;
        session()->flash('status', 'Regularización completada correctamente.');
        $this->refrescar();
    }

    public function marcarPagado(): void
    {
        $user = auth()->user();
        abort_unless($user->hasRole('Logística'), 403);

        $this->tramite->gestionLogistica->update([
            'estado_pago' => 'Pagado por Logística',
            'forma_pago' => 'Logística',
        ]);

        History::create([
            'tramite_id' => $this->tramite->id,
            'usuario_id' => $user->id,
            'accion' => 'Pago de compra registrado por Logística',
        ]);

        session()->flash('status', 'Pago registrado correctamente.');
        $this->refrescar();
    }

    public string $motivo_tesoreria = '';
    public string $medio_tesoreria = 'Transferencia';
    public string $banco_tesoreria = '';
    public string $operacion_tesoreria = '';
    public $comprobante_tesoreria;

    public function solicitarPagoTesoreria(): void
    {
        $user = auth()->user();
        abort_unless($user->hasAnyRole(['Logística', 'Sistemas']), 403);
        $gestion = $this->tramite->gestionLogistica;
        abort_unless($gestion && $gestion->monto > 0, 400);

        if ($this->tramite->solicitudesTesoreria()->where('origen', 'REQ-Compra')->where('estado', 'Pendiente')->exists()) {
            session()->flash('error', 'Ya existe una solicitud pendiente para esta compra.');
            return;
        }

        $solicitud = $this->tramite->solicitudesTesoreria()->create([
            'solicitado_por' => $user->id,
            'motivo' => $this->motivo_tesoreria ?: 'Pago de compra registrada en Logística.',
            'monto' => $gestion->monto,
            'origen' => 'REQ-Compra',
            'estado' => 'Pendiente',
            'fecha_solicitud' => now(),
        ]);

        $gestion->update(['forma_pago' => 'Tesorería', 'estado_pago' => 'Pendiente de Tesorería']);
        History::create(['tramite_id' => $this->tramite->id, 'usuario_id' => $user->id, 'accion' => "Pago solicitado a Tesorería: S/ {$gestion->monto}"]);
        session()->flash('status', 'Solicitud enviada a Tesorería.');
        $this->refrescar();
    }

    public function pagarSolicitudTesoreria(int $solicitudId): void
    {
        $user = auth()->user();
        abort_unless($user->hasRole('Tesorería'), 403);
        $solicitud = $this->tramite->solicitudesTesoreria()->findOrFail($solicitudId);
        abort_unless($solicitud->estado === 'Pendiente', 400);

        $this->validate([
            'medio_tesoreria' => 'required|string|max:50',
            'banco_tesoreria' => 'nullable|string|max:100',
            'operacion_tesoreria' => 'nullable|string|max:100',
            'comprobante_tesoreria' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        DB::transaction(function () use ($solicitud, $user) {
            $solicitud->pagos()->create([
                'nombre_original' => $this->comprobante_tesoreria->getClientOriginalName(),
                'nombre_archivo' => $this->comprobante_tesoreria->store('pagos-tesoreria', 'public'),
                'medio_pago' => $this->medio_tesoreria,
                'banco' => $this->banco_tesoreria,
                'monto' => $solicitud->monto,
                'nro_operacion' => $this->operacion_tesoreria,
            ]);
            $solicitud->update(['estado' => 'Atendida', 'fecha_atencion' => now()]);
            $this->tramite->gestionLogistica?->update(['estado_pago' => 'Pagado por Tesorería', 'forma_pago' => 'Tesorería']);
            History::create(['tramite_id' => $this->tramite->id, 'usuario_id' => $user->id, 'accion' => 'Pago de compra atendido por Tesorería']);
        });

        $this->comprobante_tesoreria = null;
        session()->flash('status', 'Pago de Tesorería registrado correctamente.');
        $this->refrescar();
    }

    public array $envio = [
        'guia_numero' => '',
        'guia_fecha' => '',
        'guia_pendiente' => false,
    ];
    public array $archivos_guia = [];

    public function enviarAObra(): void
    {
        $user = auth()->user();
        abort_unless($user->hasAnyRole(['Logística', 'Sistemas']), 403);

        $this->validate([
            'archivos_guia' => 'array|max:10',
            'archivos_guia.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,xml|max:10240',
        ]);

        $gestion = $this->tramite->gestionLogistica;

        if (! $gestion || ! in_array($gestion->estado_pago, ['Pagado por Logística', 'Pagado por Tesorería'])) {
            session()->flash('error', 'No puedes enviar a obra mientras el pago esté pendiente.');
            return;
        }

        DB::transaction(function () use ($user, $gestion) {
            $gestion->update([
                'guia_numero' => $this->envio['guia_numero'],
                'guia_fecha' => $this->envio['guia_fecha'] ?: null,
                'guia_pendiente' => $this->envio['guia_pendiente'],
                'enviado_fecha' => now(),
            ]);

            foreach ($this->archivos_guia as $archivo) {
                \App\Models\ArchivoLogistica::create([
                    'tramite_id' => $this->tramite->id,
                    'tipo' => 'guia',
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'nombre_archivo' => $archivo->store('archivos-logistica', 'public'),
                ]);
            }

            $this->tramite->update(['estado' => 'Enviado a obra']);

            History::create([
                'tramite_id' => $this->tramite->id,
                'usuario_id' => $user->id,
                'accion' => 'Requerimiento enviado a obra',
            ]);

            $this->notificarRoles(
                ['Gerencia de Obra', 'Control y Planeamiento'],
                'Requerimiento enviado a obra',
                "El requerimiento {$this->tramite->tracking} fue despachado y está en camino. Confirma la recepción cuando llegue.",
                'accion'
            );
        });

        session()->flash('status', 'Envío a obra registrado correctamente.');
        $this->refrescar();
    }

    protected function refrescar(): void
    {
        $this->tramite->refresh();
        $this->tramite->load(['approvals.usuario', 'history.usuario', 'gestionLogistica', 'gestionSp', 'attachments', 'items.imagenes', 'archivosLogistica', 'regularizaciones.responsable', 'solicitudesTesoreria.pagos']);
    }

        public function recibirEnObra(): void
    {
        $user = auth()->user();
        abort_unless($user->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Sistemas']), 403);
        abort_unless($this->tramite->tipo === 'REQ' && $this->tramite->estado === 'Enviado a obra', 400);

        DB::transaction(function () use ($user) {
            $this->tramite->update(['estado' => 'Cerrado']);

            History::create([
                'tramite_id' => $this->tramite->id,
                'usuario_id' => $user->id,
                'accion' => 'Recepción confirmada en obra',
            ]);

            $this->notificarRoles(
                ['Gerencia General', 'Administración', 'Logística', 'Tesorería'],
                'Requerimiento cerrado',
                "El requerimiento {$this->tramite->tracking} fue recibido en obra y quedó cerrado."
            );
        });

        session()->flash('status', 'Recepción en obra confirmada. Trámite cerrado.');
        $this->refrescar();
    }

        public string $asignado_pago = 'Tesorería';

    public function asignarPagoSp(): void
    {
        $user = auth()->user();
        abort_unless($user->hasRole('Gerencia General'), 403);
        abort_unless($this->tramite->tipo === 'SP' && $this->tramite->estado === 'Pendiente asignación de pago', 400);

        DB::transaction(function () use ($user) {
            \App\Models\GestionSp::updateOrCreate(
                ['tramite_id' => $this->tramite->id],
                [
                    'asignado_pago' => $this->asignado_pago,
                    'asignado_por' => $user->id,
                    'fecha_asignacion' => now(),
                ]
            );

            $nuevoEstado = $this->asignado_pago === 'Tesorería'
                ? 'Asignada a Tesorería'
                : 'Asignada a Gerencia General';

            $accion = $this->asignado_pago === 'Tesorería'
                ? 'Pago asignado a Tesorería'
                : 'Pago asumido por Gerencia General';

            $this->tramite->update(['estado' => $nuevoEstado]);

            History::create([
                'tramite_id' => $this->tramite->id,
                'usuario_id' => $user->id,
                'accion' => $accion,
            ]);
        });

        session()->flash('status', 'Responsable del pago asignado correctamente.');
        $this->refrescar();
    }

    public string $medio_pago = 'Transferencia';
    public string $banco_pago = '';
    public string $fecha_pago = '';
    public $monto_pagado = 0;
    public string $nro_operacion = '';
    public $comprobante_pago;

    public function registrarPagoSp(): void
    {
        $user = auth()->user();
        $gestion = $this->tramite->gestionSp;

        $puedePagar = false;

        if ($gestion?->asignado_pago === 'Tesorería' && in_array($this->tramite->estado, ['Asignada a Tesorería', 'Pago parcial'])) {
            $puedePagar = $user->hasRole('Tesorería');
        } elseif ($gestion?->asignado_pago === 'Gerencia General' && in_array($this->tramite->estado, ['Asignada a Gerencia General', 'Pago parcial'])) {
            $puedePagar = $user->hasRole('Gerencia General');
        }

        abort_unless($puedePagar, 403);

        $this->validate([
            'medio_pago' => 'required|in:Transferencia,Depósito,Efectivo,Yape,Plin,Otro',
            'fecha_pago' => 'required|date',
            'monto_pagado' => 'required|numeric|min:0.01',
            'comprobante_pago' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ], [
            'medio_pago.required' => 'Selecciona un medio de pago.',
            'medio_pago.in' => 'Selecciona un medio de pago válido.',
            'fecha_pago.required' => 'Ingresa la fecha del pago.',
            'fecha_pago.date' => 'La fecha del pago no es válida.',
            'monto_pagado.required' => 'Ingresa el monto pagado.',
            'monto_pagado.min' => 'El monto pagado no es válido.',
            'comprobante_pago.required' => 'Debes adjuntar el comprobante de pago.',
            'comprobante_pago.mimes' => 'El comprobante debe ser PDF o imagen (jpg, png, webp).',
            'comprobante_pago.max' => 'El comprobante no debe superar 10 MB.',
        ]);

        $montoSolicitado = (float) $this->tramite->abono;
        $pagadoAnterior = (float) $gestion->pagosMultiples()->sum('monto');
        if ($pagadoAnterior === 0.0 && $gestion->pagado_por) {
            $pagadoAnterior = (float) $gestion->monto_pagado;
        }
        $montoPendiente = round($montoSolicitado - $pagadoAnterior, 2);

        if ((float) $this->monto_pagado <= 0 || (float) $this->monto_pagado > $montoPendiente + 0.01) {
            $this->addError('monto_pagado', 'El monto no puede superar el saldo pendiente de S/ ' . number_format($montoPendiente, 2) . '.');
            return;
        }

        DB::transaction(function () use ($user, $gestion, $montoSolicitado, $pagadoAnterior) {
            $nombreOriginal = $this->comprobante_pago->getClientOriginalName();
            $nombreArchivo = $this->comprobante_pago->store('comprobantes-sp', 'public');
            $montoActual = (float) $this->monto_pagado;

            $gestion->pagosMultiples()->create([
                'pagado_por' => $user->id,
                'medio_pago' => $this->medio_pago,
                'banco' => $this->banco_pago,
                'nro_operacion' => $this->nro_operacion,
                'monto' => $montoActual,
                'fecha_pago' => $this->fecha_pago,
                'nombre_original' => $nombreOriginal,
                'nombre_archivo' => $nombreArchivo,
            ]);

            $gestion->update([
                'pagado_por' => $user->id,
                'medio_pago' => $this->medio_pago,
                'banco_pago' => $this->banco_pago,
                'nro_operacion' => $this->nro_operacion,
                'monto_pagado' => $pagadoAnterior + $montoActual,
                'fecha_pago' => $this->fecha_pago,
                'nombre_original_pago' => $nombreOriginal,
                'nombre_archivo_pago' => $nombreArchivo,
            ]);

            $estado = round($pagadoAnterior + $montoActual, 2) >= round($montoSolicitado, 2)
                ? 'Pagada pendiente conformidad GG'
                : 'Pago parcial';
            $this->tramite->update(['estado' => $estado]);

            History::create([
                'tramite_id' => $this->tramite->id,
                'usuario_id' => $user->id,
                'accion' => $estado === 'Pago parcial' ? 'Pago parcial de solicitud registrado' : 'Pago total de solicitud registrado',
            ]);
        });

        session()->flash('status', 'Pago registrado correctamente.');
        $this->refrescar();
    }

    public function confirmarPagoSp(): void
    {
        $user = auth()->user();
        abort_unless($user->hasRole('Gerencia General'), 403);
        abort_unless($this->tramite->tipo === 'SP' && $this->tramite->estado === 'Pagada pendiente conformidad GG', 400);

        $gestion = $this->tramite->gestionSp;

        if (! $gestion?->pagado_por || ! $gestion?->nombre_archivo_pago) {
            session()->flash('error', 'La solicitud aún no tiene un pago registrado con comprobante.');
            return;
        }

        DB::transaction(function () use ($user, $gestion) {
            $gestion->update([
                'conformidad_gg' => true,
                'fecha_conformidad' => now(),
            ]);

            $this->tramite->update(['estado' => 'Cerrado']);

            History::create([
                'tramite_id' => $this->tramite->id,
                'usuario_id' => $user->id,
                'accion' => 'Conformidad final registrada por Gerencia General',
            ]);

            $this->notificarRoles(
                ['Control y Planeamiento', 'Gerencia de Obra'],
                'Solicitud de pago cerrada',
                "La solicitud {$this->tramite->tracking} recibió la conformidad final de Gerencia General y quedó cerrada."
            );
        });

        session()->flash('status', 'Solicitud cerrada con conformidad de Gerencia General.');
        $this->refrescar();
    }

    public function estadoColor(string $estado): string
    {
        return match ($estado) {
            'Pendiente de aprobación' => 'yellow',
            'Aprobado', 'En oficina', 'Cotización', 'Comprado' => 'blue',
            'Pendiente asignación de pago' => 'purple',
            'Enviado a obra' => 'indigo',
            'Cerrado' => 'green',
            default => 'zinc',
        };
    }
    
};
?>
<div>
    <div class="mb-6 flex items-start justify-between">
        <div>
            <flux:heading size="xl">{{ $tramite->tracking }}</flux:heading>
            <flux:text class="text-zinc-500">{{ $tramite->tipo === 'REQ' ? 'Requerimiento' : 'Solicitud de Pago' }} · {{ $tramite->obra->nombre ?? 'Sin obra' }} · N° {{ $tramite->numero }} · {{ $tramite->fecha->format('Y-m-d') }}</flux:text>
        </div>
        <div class="flex flex-col items-end gap-2">
            <flux:badge size="lg" :color="$this->estadoColor($tramite->estado)">{{ $tramite->estado }}</flux:badge>
            @if ($tramite->estado === 'Pendiente de mi revisión' && $tramite->creador_id === auth()->id())
                <div class="flex gap-2">
                    <flux:button size="sm" href="{{ route('tramites.edit', $tramite) }}" wire:navigate>Editar</flux:button>
                    <flux:button size="sm" variant="primary" wire:click="creatorApprove">Confirmar revisión</flux:button>
                    <flux:button size="sm" variant="danger" wire:click="deleteRequest">Eliminar</flux:button>
                </div>
            @endif
            @if (in_array($tramite->tipo, ['REQ', 'SP']))
                <flux:button size="sm" icon="arrow-down-tray" href="{{ route('tramites.pdf', $tramite) }}">Descargar PDF</flux:button>
            @endif
            <flux:button size="sm" variant="ghost" href="{{ route('tramites.tracking', $tramite) }}" wire:navigate>Seguimiento</flux:button>
        </div>
    </div>

    @if (session('status'))
        <flux:callout variant="success" class="mb-4" heading="{{ session('status') }}" />
    @endif

    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-3">Ítems solicitados</flux:heading>
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-zinc-500">
                <tr>
                    <th class="py-2">Sección</th>
                    <th class="py-2">Descripción</th>
                    <th class="py-2">Unidad</th>
                    <th class="py-2 text-right">Cant.</th>
                    <th class="py-2 text-right">Stock</th>
                    <th class="py-2 text-right">Comprar</th>
                    <th class="py-2">Referencias</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($tramite->items as $item)
                    <tr>
                        <td class="py-2">{{ $item->seccion }}</td>
                        <td class="py-2">{{ $item->descripcion }}</td>
                        <td class="py-2">{{ $item->unidad }}</td>
                        <td class="py-2 text-right">{{ $item->cantidad }}</td>
                        <td class="py-2 text-right">{{ $item->stock }}</td>
                        <td class="py-2 text-right font-semibold">{{ $item->comprar }}</td>
                        <td class="py-2">
                            @foreach ($item->imagenes as $imagen)
                                <a class="me-2 underline" href="{{ route('items.images.download', [$item, $imagen]) }}">{{ $imagen->nombre_original }}</a>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </flux:card>

    @if ($tramite->attachments->isNotEmpty())
        <flux:card class="mb-6">
            <flux:heading size="lg" class="mb-3">Documentos de respaldo</flux:heading>
            <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($tramite->attachments->sortBy('orden') as $adjunto)
                    <a href="{{ route('tramites.attachments.download', [$tramite, $adjunto]) }}" class="flex items-center justify-between gap-3 py-3 text-sm hover:text-emerald-600">
                        <span class="truncate">{{ $adjunto->nombre_original }}</span>
                        <flux:icon.arrow-down-tray class="size-4 shrink-0" />
                    </a>
                @endforeach
            </div>
        </flux:card>
    @endif

    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-3">Vistos buenos</flux:heading>
        <div class="flex flex-col gap-3">
            @foreach ($tramite->approvals as $approval)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div>
                        <div class="font-medium">{{ $approval->rol }}</div>
                        <div class="text-sm text-zinc-500">{{ $approval->usuario->name }}</div>
                    </div>

                    @if ($approval->aprobado)
                        <flux:badge color="green" icon="check">Aprobado {{ $approval->fecha_aprobacion?->format('Y-m-d H:i') }}</flux:badge>
                    @elseif ($approval->usuario_id === auth()->id() || auth()->user()->hasRole('Sistemas'))
                        <flux:button size="sm" variant="primary" wire:click="aprobar({{ $approval->id }})">
                            Dar visto bueno
                        </flux:button>
                    @else
                        <flux:badge color="zinc">Pendiente</flux:badge>
                    @endif
                </div>
            @endforeach
        </div>
    </flux:card>

    @if ($tramite->tipo === 'REQ' && in_array($tramite->estado, ['Aprobado', 'Recibido por Logística', 'Cotizaciones en gestión', 'Pendiente de Administración', 'En gestión de compra', 'Enviado a obra']))
    <flux:card class="mb-6">
        <div class="mb-3 flex items-center justify-between">
            <flux:heading size="lg">Gestión de Logística</flux:heading>

            @if (auth()->user()->hasAnyRole(['Logística', 'Administración', 'Sistemas']))
                <flux:button size="sm" variant="ghost" icon="document-text" :href="route('tramites.quotations', $tramite)" wire:navigate>
                    Gestionar cotizaciones
                </flux:button>
            @endif
        </div>

        @if (session('error'))
            <flux:callout variant="danger" class="mb-4" heading="{{ session('error') }}" />
        @endif

        @if ($tramite->estado === 'Aprobado')
            @if (auth()->user()->hasAnyRole(['Logística', 'Sistemas']))
                <flux:button variant="primary" wire:click="recibirLogistica">
                    Marcar como recibido por Logística
                </flux:button>
            @else
                <flux:text class="text-zinc-500">Pendiente de recepción por Logística.</flux:text>
            @endif
        @endif

        @if ($tramite->estado === 'Recibido por Logística' && auth()->user()->hasRole('Logística'))
            <form wire:submit="registrarCompra" class="flex flex-col gap-4">
                <flux:heading size="sm">Registrar compra</flux:heading>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <flux:input type="date" label="Fecha de compra" wire:model="compra.fecha_compra" />
                    <flux:select label="Tipo de comprobante" wire:model="compra.tipo_comprobante">
                        <flux:select.option value="Factura">Factura</flux:select.option>
                        <flux:select.option value="Boleta">Boleta</flux:select.option>
                        <flux:select.option value="Otro">Otro</flux:select.option>
                    </flux:select>
                    <flux:input label="N° de comprobante" wire:model="compra.nro_comprobante" />
                    <flux:input type="number" step="0.01" label="Monto (S/)" wire:model="compra.monto" />
                </div>
                <div class="space-y-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:heading size="sm">Detalle de cantidades compradas</flux:heading>
                    @foreach ($tramite->items->where('comprar', '>', 0) as $item)
                        <div class="grid gap-3 sm:grid-cols-3">
                            <flux:text class="self-center">{{ $item->descripcion }} <span class="text-zinc-500">(máx. {{ $item->comprar }})</span></flux:text>
                            <flux:input type="number" step="0.01" label="Cantidad" wire:model="detalle_compra.{{ $item->id }}.cantidad" />
                            <flux:input label="Observación" wire:model="detalle_compra.{{ $item->id }}.observacion" />
                        </div>
                    @endforeach
                </div>
                <flux:checkbox wire:model="compra.pendiente_regularizacion" label="Pendiente de regularización (falta documentación definitiva)" />
                <div><flux:label>Comprobantes de compra (opcional)</flux:label><input type="file" wire:model="archivos_compra" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.xml" class="mt-1 block w-full text-sm" />@error('archivos_compra.*') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror</div>
                <div>
                    <flux:button type="submit" variant="primary">Registrar compra</flux:button>
                </div>
            </form>
        @endif

        @if ($tramite->estado === 'En gestión de compra' && $tramite->gestionLogistica)
            <div class="flex flex-col gap-3">
                <flux:text>
                    Compra: {{ $tramite->gestionLogistica->tipo_comprobante }} {{ $tramite->gestionLogistica->nro_comprobante }} ·
                    S/ {{ number_format($tramite->gestionLogistica->monto, 2) }} ·
                    {{ $tramite->gestionLogistica->fecha_compra?->format('Y-m-d') }}
                </flux:text>

                @if ($tramite->gestionLogistica->estado_pago && auth()->user()->hasRole('Logística'))
                    <form wire:submit="registrarComprobantesDefinitivos" class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <div class="flex items-center justify-between"><flux:heading size="sm">Comprobantes definitivos</flux:heading><flux:button type="button" size="sm" icon="plus" wire:click="addComprobanteDefinitivo">Agregar comprobante</flux:button></div>
                        @foreach ($comprobantes_definitivos as $index => $comprobante)
                            <div class="grid gap-3 rounded border border-zinc-200 p-3 sm:grid-cols-2 dark:border-zinc-700">
                                <flux:input type="date" label="Fecha" wire:model="comprobantes_definitivos.{{ $index }}.fecha_compra" />
                                <flux:select label="Tipo" wire:model="comprobantes_definitivos.{{ $index }}.tipo_comprobante"><flux:select.option value="Factura">Factura</flux:select.option><flux:select.option value="Boleta">Boleta</flux:select.option><flux:select.option value="Otro">Otro</flux:select.option></flux:select>
                                <flux:input label="Número" wire:model="comprobantes_definitivos.{{ $index }}.nro_comprobante" />
                                <flux:input type="number" step="0.01" label="Monto" wire:model="comprobantes_definitivos.{{ $index }}.monto" />
                                <div class="sm:col-span-2"><flux:label>Evidencia</flux:label><input type="file" wire:model="comprobantes_definitivos.{{ $index }}.archivo" accept=".pdf,.jpg,.jpeg,.png,.webp,.xml" class="mt-1 block w-full text-sm" /></div>
                            </div>
                        @endforeach
                        @if ($comprobantes_definitivos)<flux:button type="submit" variant="primary">Registrar comprobantes</flux:button>@endif
                    </form>
                @endif

                @if ($tramite->gestionLogistica->estado_pago)
                    <flux:badge color="green">{{ $tramite->gestionLogistica->estado_pago }}</flux:badge>

                    <form wire:submit="enviarAObra" class="mt-3 flex flex-col gap-4">
                        <flux:heading size="sm">Enviar a obra</flux:heading>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <flux:input label="N° de guía" wire:model="envio.guia_numero" />
                            <flux:input type="date" label="Fecha de guía" wire:model="envio.guia_fecha" />
                        </div>
                        <flux:checkbox wire:model="envio.guia_pendiente" label="Guía pendiente de regularización" />
                        <div><flux:label>Guía o evidencia de despacho (opcional)</flux:label><input type="file" wire:model="archivos_guia" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.xml" class="mt-1 block w-full text-sm" />@error('archivos_guia.*') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror</div>
                        <div>
                            <flux:button type="submit" variant="primary">Enviar a obra</flux:button>
                        </div>
                    </form>
                @elseif (auth()->user()->hasRole('Logística'))
                    <div class="flex flex-col gap-3">
                        <flux:button size="sm" wire:click="marcarPagado">Marcar compra como pagada</flux:button>
                        <form wire:submit="solicitarPagoTesoreria" class="flex flex-col gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                            <flux:input label="Motivo para Tesorería (opcional)" wire:model="motivo_tesoreria" />
                            <flux:button type="submit" size="sm">Solicitar pago a Tesorería</flux:button>
                        </form>
                    </div>
                @else
                    <flux:badge color="yellow">Pago pendiente</flux:badge>
                @endif
            </div>
        @endif
        
        @if ($tramite->estado === 'Enviado a obra')
        <div class="flex flex-col gap-3">
            <flux:text>
                Guía: {{ $tramite->gestionLogistica->guia_numero ?? '—' }} ·
                Enviado el {{ $tramite->gestionLogistica->enviado_fecha?->format('Y-m-d H:i') }}
            </flux:text>

            @if (auth()->user()->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Sistemas']))
                <div>
                    <flux:button variant="primary" wire:click="recibirEnObra">
                        Confirmar recepción en obra
                    </flux:button>
                </div>
            @else
                <flux:badge color="purple">Pendiente de recepción en obra</flux:badge>
            @endif
        </div>
    @endif
    </flux:card>
    @endif

    @if ($tramite->tipo === 'SP' && in_array($tramite->estado, ['Pendiente asignación de pago', 'Asignada a Tesorería', 'Asignada a Gerencia General', 'Pago parcial', 'Pagada pendiente conformidad GG']))
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-3">Gestión de Pago</flux:heading>

        @if (session('error'))
            <flux:callout variant="danger" class="mb-4" heading="{{ session('error') }}" />
        @endif

        @if ($tramite->estado === 'Pendiente asignación de pago')
            @if (auth()->user()->hasRole('Gerencia General'))
                <form wire:submit="asignarPagoSp" class="flex flex-col gap-4">
                    <flux:select label="¿Quién realizará el pago?" wire:model="asignado_pago">
                        <flux:select.option value="Tesorería">Tesorería</flux:select.option>
                        <flux:select.option value="Gerencia General">Gerencia General</flux:select.option>
                    </flux:select>
                    <div>
                        <flux:button type="submit" variant="primary">Asignar responsable de pago</flux:button>
                    </div>
                </form>
            @else
                <flux:text class="text-zinc-500">Pendiente de asignación por Gerencia General.</flux:text>
            @endif
        @endif

        @if (in_array($tramite->estado, ['Asignada a Tesorería', 'Asignada a Gerencia General', 'Pago parcial']))
            @php
                $puedeRegistrarPago =
                    (in_array($tramite->estado, ['Asignada a Tesorería', 'Pago parcial']) && $tramite->gestionSp?->asignado_pago === 'Tesorería' && auth()->user()->hasRole('Tesorería')) ||
                    (in_array($tramite->estado, ['Asignada a Gerencia General', 'Pago parcial']) && $tramite->gestionSp?->asignado_pago === 'Gerencia General' && auth()->user()->hasRole('Gerencia General'));
            @endphp

            @if ($puedeRegistrarPago)
                <form wire:submit="registrarPagoSp" class="flex flex-col gap-4">
                    <flux:text class="text-zinc-500">Monto solicitado: <span class="font-semibold text-zinc-900 dark:text-white">S/ {{ number_format($tramite->abono, 2) }}</span> · Pagado: S/ {{ number_format($tramite->gestionSp?->monto_pagado ?? 0, 2) }}</flux:text>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <flux:select label="Medio de pago" wire:model="medio_pago">
                            <flux:select.option value="Transferencia">Transferencia</flux:select.option>
                            <flux:select.option value="Depósito">Depósito</flux:select.option>
                            <flux:select.option value="Efectivo">Efectivo</flux:select.option>
                            <flux:select.option value="Yape">Yape</flux:select.option>
                            <flux:select.option value="Plin">Plin</flux:select.option>
                            <flux:select.option value="Otro">Otro</flux:select.option>
                        </flux:select>
                        <flux:input label="Banco" wire:model="banco_pago" />
                        <flux:input type="date" label="Fecha de pago" wire:model="fecha_pago" />
                        <flux:input type="number" step="0.01" label="Monto pagado (S/)" wire:model="monto_pagado" />
                        <flux:input label="N° de operación" wire:model="nro_operacion" />
                        <div class="flex flex-col gap-1">
                            <flux:label>Comprobante de pago (PDF o imagen)</flux:label>
                            <input type="file" wire:model="comprobante_pago" class="block w-full text-sm text-zinc-700 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-900 file:px-3 file:py-2 file:text-sm file:text-white hover:file:bg-zinc-700 dark:text-zinc-300 dark:file:bg-white dark:file:text-zinc-900" />

                            <div wire:loading wire:target="comprobante_pago" class="text-xs text-zinc-500">
                                Subiendo archivo...
                            </div>

                            @if ($comprobante_pago)
                                <div class="text-xs text-emerald-600">
                                    Archivo seleccionado: {{ $comprobante_pago->getClientOriginalName() }}
                                </div>
                            @endif

                            @error('comprobante_pago') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
                        </div>
                    </div>

                    <div>
                        <flux:button type="submit" variant="primary">Registrar pago</flux:button>
                    </div>
                </form>
            @else
                <flux:badge color="purple">Pendiente de registro de pago</flux:badge>
            @endif
        @endif

        @if ($tramite->estado === 'Pagada pendiente conformidad GG')
            <div class="flex flex-col gap-3">
                <flux:text>
                    Pago registrado: {{ $tramite->gestionSp->medio_pago }} ·
                    S/ {{ number_format($tramite->gestionSp->monto_pagado, 2) }} ·
                    {{ $tramite->gestionSp->fecha_pago?->format('Y-m-d') }}
                </flux:text>

                @if (auth()->user()->hasRole('Gerencia General'))
                    <div>
                        <flux:button variant="primary" wire:click="confirmarPagoSp">
                            Confirmar conformidad final
                        </flux:button>
                    </div>
                @else
                    <flux:badge color="yellow">Pendiente de conformidad de Gerencia General</flux:badge>
                @endif
            </div>
        @endif
    </flux:card>
    @endif

    @if ($tramite->regularizaciones->isNotEmpty())
        <flux:card class="mb-6">
            <flux:heading size="lg" class="mb-3">Regularizaciones</flux:heading>
            <div class="flex flex-col gap-4">
                @foreach ($tramite->regularizaciones->sortByDesc('created_at') as $regularizacion)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div class="font-medium">{{ $regularizacion->tipo }}</div>
                                <div class="text-sm text-zinc-500">Responsable: {{ $regularizacion->responsable->name }}</div>
                            </div>
                            <flux:badge :color="$regularizacion->estado === 'Pendiente' ? 'yellow' : 'green'">{{ $regularizacion->estado }}</flux:badge>
                        </div>
                        <div class="mt-2 text-sm text-zinc-500">{{ $regularizacion->descripcion }}</div>
                        @if ($regularizacion->estado === 'Pendiente' && $regularizacion->responsable_id === auth()->id())
                            <form wire:submit="regularizar({{ $regularizacion->id }})" class="mt-4 flex flex-col gap-3">
                                <div class="flex items-center justify-between"><flux:label>Detalle de compra</flux:label><flux:button type="button" size="sm" icon="plus" wire:click="addDetalleRegularizacion">Agregar línea</flux:button></div>
                                @foreach ($detalle_regularizacion as $index => $detalle)
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <flux:input label="Descripción" wire:model="detalle_regularizacion.{{ $index }}.descripcion" />
                                        <flux:input type="number" step="0.01" label="Cantidad" wire:model="detalle_regularizacion.{{ $index }}.cantidad" />
                                        <flux:input type="number" step="0.01" label="Precio unitario" wire:model="detalle_regularizacion.{{ $index }}.precio_unitario" />
                                    </div>
                                @endforeach
                                <div class="flex-1">
                                    <flux:label>Documento de regularización</flux:label>
                                    <input type="file" wire:model="archivo_regularizacion" accept=".pdf,.jpg,.jpeg,.png,.webp,.xml" class="mt-1 block w-full text-sm" />
                                    @error('archivo_regularizacion') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror
                                </div>
                                <flux:button type="submit" variant="primary">Completar regularización</flux:button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    @foreach ($tramite->solicitudesTesoreria->where('estado', 'Pendiente') as $solicitud)
        @if (auth()->user()->hasRole('Tesorería'))
            <flux:card class="mb-6">
                <flux:heading size="lg" class="mb-3">Pago pendiente de Tesorería</flux:heading>
                <flux:text class="mb-4">Monto solicitado: S/ {{ number_format($solicitud->monto, 2) }}</flux:text>
                <form wire:submit="pagarSolicitudTesoreria({{ $solicitud->id }})" class="grid gap-3 sm:grid-cols-2">
                    <flux:select label="Medio de pago" wire:model="medio_tesoreria">
                        <flux:select.option value="Transferencia">Transferencia</flux:select.option>
                        <flux:select.option value="Depósito">Depósito</flux:select.option>
                        <flux:select.option value="Efectivo">Efectivo</flux:select.option>
                    </flux:select>
                    <flux:input label="Banco" wire:model="banco_tesoreria" />
                    <flux:input label="N° de operación" wire:model="operacion_tesoreria" />
                    <div><flux:label>Comprobante</flux:label><input type="file" wire:model="comprobante_tesoreria" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full text-sm" />@error('comprobante_tesoreria') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror</div>
                    <div class="sm:col-span-2"><flux:button type="submit" variant="primary">Registrar pago</flux:button></div>
                </form>
            </flux:card>
        @endif
    @endforeach

    @if ($tramite->archivosLogistica->isNotEmpty())
        <flux:card class="mb-6">
            <flux:heading size="lg" class="mb-3">Archivos de Logística</flux:heading>
            <div class="flex flex-col divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($tramite->archivosLogistica->sortByDesc('created_at') as $archivo)
                    <a href="{{ asset('storage/' . $archivo->nombre_archivo) }}" target="_blank" class="flex items-center justify-between gap-3 py-3 text-sm hover:text-emerald-600">
                        <span class="truncate">{{ $archivo->tipo }}: {{ $archivo->nombre_original }}</span>
                        <flux:icon.arrow-top-right-on-square class="size-4 shrink-0" />
                    </a>
                @endforeach
            </div>
        </flux:card>
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-3">Historial</flux:heading>
        <div class="flex flex-col gap-2">
            @forelse ($tramite->history->sortByDesc('created_at') as $h)
                <div class="flex items-start gap-3 text-sm">
                    <flux:icon.clock class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                    <div>
                        <span class="font-medium">{{ $h->usuario->name }}</span>
                        — {{ $h->accion }}
                        <div class="text-xs text-zinc-500">{{ $h->created_at->format('Y-m-d H:i') }}</div>
                    </div>
                </div>
            @empty
                <flux:text class="text-zinc-500">Sin movimientos aún.</flux:text>
            @endforelse
        </div>
    </flux:card>
</div>