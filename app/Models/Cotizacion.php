<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    protected $fillable = ['tramite_id', 'proveedor_id', 'creado_por', 'tipo_sustento', 'fecha', 'estado', 'observacion', 'enviado_fecha'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'enviado_fecha' => 'datetime'];
    }

    public function tramite(): BelongsTo { return $this->belongsTo(Tramite::class); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function creador(): BelongsTo { return $this->belongsTo(User::class, 'creado_por'); }
    public function items(): HasMany { return $this->hasMany(CotizacionItem::class); }
    public function archivos(): HasMany { return $this->hasMany(CotizacionArchivo::class); }
}