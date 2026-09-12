<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GestionLogistica extends Model
{
    protected $table = 'gestion_logistica';

    protected $fillable = [
        'tramite_id', 'recibido_fecha', 'cotizacion_estado', 'proveedor', 'ruc',
        'fecha_compra', 'tipo_comprobante', 'nro_comprobante', 'monto',
        'comprobante_pendiente', 'forma_pago', 'estado_pago', 'guia_numero',
        'guia_fecha', 'guia_pendiente', 'enviado_fecha', 'requiere_reembolso',
        'medio_envio', 'responsable_transporte', 'costo_envio', 'observacion_envio',
    ];

    protected function casts(): array
    {
        return [
            'recibido_fecha' => 'datetime',
            'fecha_compra' => 'date',
            'guia_fecha' => 'date',
            'enviado_fecha' => 'datetime',
            'comprobante_pendiente' => 'boolean',
            'guia_pendiente' => 'boolean',
            'requiere_reembolso' => 'boolean',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }
}
