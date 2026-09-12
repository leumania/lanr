<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionItem extends Model
{
    protected $fillable = ['cotizacion_id', 'item_id', 'cantidad', 'precio_unitario'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2', 'precio_unitario' => 'decimal:2'];
    }

    public function cotizacion(): BelongsTo { return $this->belongsTo(Cotizacion::class); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}