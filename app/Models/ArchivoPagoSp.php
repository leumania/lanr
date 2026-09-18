<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivoPagoSp extends Model
{
    protected $table = 'archivos_pago_sp';

    protected $fillable = ['gestion_sp_id', 'nombre_original', 'nombre_archivo'];

    public function gestion(): BelongsTo
    {
        return $this->belongsTo(GestionSp::class, 'gestion_sp_id');
    }
}
