<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionArchivo extends Model
{
    protected $table = 'cotizacion_archivos';

    protected $fillable = ['cotizacion_id', 'nombre_original', 'nombre_archivo'];

    public function cotizacion(): BelongsTo { return $this->belongsTo(Cotizacion::class); }
}