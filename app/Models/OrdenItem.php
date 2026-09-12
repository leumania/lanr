<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenItem extends Model
{
    protected $table = 'orden_items';

    protected $fillable = ['orden_id', 'descripcion', 'unidad', 'cantidad', 'precio_unitario', 'monto'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2', 'precio_unitario' => 'decimal:2', 'monto' => 'decimal:2'];
    }

    public function orden(): BelongsTo { return $this->belongsTo(Orden::class); }
}