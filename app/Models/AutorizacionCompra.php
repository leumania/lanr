<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutorizacionCompra extends Model
{
    protected $table = 'autorizaciones_compra';

    protected $fillable = ['tramite_id', 'autorizado_por', 'estado', 'motivo_anulacion', 'fecha', 'anulada_fecha'];

    protected function casts(): array
    {
        return ['fecha' => 'datetime', 'anulada_fecha' => 'datetime'];
    }

    public function tramite(): BelongsTo { return $this->belongsTo(Tramite::class); }
    public function autorizador(): BelongsTo { return $this->belongsTo(User::class, 'autorizado_por'); }
    public function items(): HasMany { return $this->hasMany(AutorizacionItem::class, 'autorizacion_id'); }
}