<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Orden extends Model
{
    protected $table = 'ordenes';

    protected $fillable = ['obra_id', 'tipo_orden', 'numero', 'fecha', 'proveedor', 'documento_proveedor', 'moneda', 'descripcion', 'total', 'estado', 'creador_id', 'vobo_gerencia_obra', 'vobo_administracion', 'vobo_gerencia_general'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'total' => 'decimal:2'];
    }

    public function obra(): BelongsTo { return $this->belongsTo(Obra::class); }
    public function creador(): BelongsTo { return $this->belongsTo(User::class, 'creador_id'); }
    public function items(): HasMany { return $this->hasMany(OrdenItem::class); }
    public function adjuntos(): HasMany { return $this->hasMany(OrdenAdjunto::class); }
    public function gerenciaObra(): BelongsTo { return $this->belongsTo(User::class, 'vobo_gerencia_obra'); }
    public function administracion(): BelongsTo { return $this->belongsTo(User::class, 'vobo_administracion'); }
    public function gerenciaGeneral(): BelongsTo { return $this->belongsTo(User::class, 'vobo_gerencia_general'); }
}