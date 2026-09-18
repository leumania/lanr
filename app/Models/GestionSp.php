<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GestionSp extends Model
{
    protected $table = 'gestion_sp';

    protected $fillable = [
        'tramite_id', 'asignado_pago', 'asignado_por', 'fecha_asignacion',
        'pagado_por', 'medio_pago', 'banco_pago', 'nro_operacion',
        'monto_pagado', 'fecha_pago', 'nombre_original_pago',
        'nombre_archivo_pago', 'conformidad_gg', 'fecha_conformidad',
    ];

    protected function casts(): array
    {
        return [
            'fecha_asignacion' => 'datetime',
            'fecha_pago' => 'datetime',
            'fecha_conformidad' => 'datetime',
            'conformidad_gg' => 'boolean',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    public function pagadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pagado_por');
    }

    public function pagosMultiples(): HasMany
    {
        return $this->hasMany(SpPagoMultiple::class, 'gestion_sp_id');
    }

    public function archivosPago(): HasMany
    {
        return $this->hasMany(ArchivoPagoSp::class, 'gestion_sp_id');
    }
}
