<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutorizacionItem extends Model
{
    protected $table = 'autorizacion_items';

    protected $fillable = ['autorizacion_id', 'cotizacion_id', 'item_id', 'proveedor_id', 'cantidad', 'precio_unitario', 'subtotal'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2', 'precio_unitario' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public function autorizacion(): BelongsTo { return $this->belongsTo(AutorizacionCompra::class); }
    public function cotizacion(): BelongsTo { return $this->belongsTo(Cotizacion::class); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
}