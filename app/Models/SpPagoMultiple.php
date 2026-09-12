<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpPagoMultiple extends Model
{
    protected $table = 'sp_pagos_multiples';

    protected $fillable = ['gestion_sp_id', 'pagado_por', 'medio_pago', 'banco', 'nro_operacion', 'monto', 'fecha_pago', 'nombre_original', 'nombre_archivo'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'fecha_pago' => 'date'];
    }

    public function gestion(): BelongsTo { return $this->belongsTo(GestionSp::class, 'gestion_sp_id'); }
    public function pagador(): BelongsTo { return $this->belongsTo(User::class, 'pagado_por'); }
}