<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivoLogistica extends Model
{
    protected $table = 'archivos_logistica';

    protected $fillable = ['tramite_id', 'tipo', 'nombre_original', 'nombre_archivo'];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }
}