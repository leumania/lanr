<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegularizacionDetalle extends Model
{
    protected $table = 'regularizacion_detalles';
    protected $fillable = ['regularizacion_id', 'descripcion', 'cantidad', 'precio_unitario', 'precio_total'];
    protected function casts(): array { return ['cantidad' => 'decimal:2', 'precio_unitario' => 'decimal:2', 'precio_total' => 'decimal:2']; }
    public function regularizacion(): BelongsTo { return $this->belongsTo(Regularizacion::class); }
}