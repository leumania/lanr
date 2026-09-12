<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoTesoreria extends Model
{
    protected $table = 'pagos_tesoreria';

    protected $fillable = [
        'solicitud_id', 'nombre_original', 'nombre_archivo',
        'medio_pago', 'banco', 'monto', 'nro_operacion',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudTesoreria::class, 'solicitud_id');
    }
}