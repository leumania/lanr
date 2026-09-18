<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Despacho extends Model
{
    protected $table = 'despachos_logistica';

    protected $fillable = [
        'tramite_id', 'proveedor_id', 'medio_envio', 'responsable_transporte',
        'costo_envio', 'observacion', 'guia_numero', 'guia_fecha', 'guia_pendiente', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'guia_fecha' => 'date',
            'guia_pendiente' => 'boolean',
            'costo_envio' => 'decimal:2',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DespachoDetalle::class, 'despacho_id');
    }
}
