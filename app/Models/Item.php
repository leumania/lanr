<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'tramite_id', 'seccion', 'nro', 'descripcion', 'unidad',
        'cantidad', 'stock', 'comprar', 'justificacion', 'prioridad', 'fecha_requerida', 'item_key', 'costo', 'monto', 'nro_despacho',
    ];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(CotizacionItem::class);
    }

    public function autorizaciones(): HasMany
    {
        return $this->hasMany(AutorizacionItem::class);
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ItemImagen::class);
    }
}