<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reembolso extends Model
{
    protected $table = 'reembolsos_rendiciones';

    protected $fillable = ['obra_id', 'tipo', 'numero', 'fecha', 'solicitante_id', 'concepto', 'monto', 'moneda', 'observaciones', 'estado', 'autorizado_por', 'fecha_autorizacion', 'atendido_por', 'fecha_atencion'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2', 'fecha_autorizacion' => 'datetime', 'fecha_atencion' => 'datetime'];
    }

    public function obra(): BelongsTo { return $this->belongsTo(Obra::class); }
    public function solicitante(): BelongsTo { return $this->belongsTo(User::class, 'solicitante_id'); }
    public function autorizador(): BelongsTo { return $this->belongsTo(User::class, 'autorizado_por'); }
    public function atendiente(): BelongsTo { return $this->belongsTo(User::class, 'atendido_por'); }
    public function adjuntos(): HasMany { return $this->hasMany(ReembolsoAdjunto::class, 'reembolso_id'); }
}