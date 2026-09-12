<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReembolsoAdjunto extends Model
{
    protected $table = 'reembolso_adjuntos';

    protected $fillable = ['reembolso_id', 'tipo', 'nombre_original', 'nombre_archivo'];

    public function reembolso(): BelongsTo { return $this->belongsTo(Reembolso::class, 'reembolso_id'); }
}