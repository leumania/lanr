<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudTesoreria extends Model
{
    protected $table = 'solicitudes_tesoreria';

    protected $fillable = [
        'tramite_id', 'autorizacion_id', 'solicitado_por', 'motivo', 'monto', 'estado',
        'origen', 'fecha_solicitud', 'fecha_atencion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'datetime',
            'fecha_atencion' => 'datetime',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function autorizacion(): BelongsTo
    {
        return $this->belongsTo(AutorizacionCompra::class, 'autorizacion_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoTesoreria::class, 'solicitud_id');
    }
}