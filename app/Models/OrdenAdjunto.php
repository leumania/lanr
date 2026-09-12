<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenAdjunto extends Model
{
    protected $table = 'orden_adjuntos';

    protected $fillable = ['orden_id', 'nombre_original', 'nombre_archivo'];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
    }
}