<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompraLogistica extends Model
{
    protected $table = 'compras_logistica';

    protected $fillable = ['tramite_id', 'proveedor_id', 'fecha_compra', 'tipo_comprobante', 'nro_comprobante', 'monto', 'nombre_original', 'nombre_archivo', 'creado_por'];

    protected function casts(): array
    {
        return ['fecha_compra' => 'date', 'monto' => 'decimal:2'];
    }

    public function tramite(): BelongsTo { return $this->belongsTo(Tramite::class); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function creador(): BelongsTo { return $this->belongsTo(User::class, 'creado_por'); }
    public function detalles(): HasMany { return $this->hasMany(CompraDetalle::class, 'compra_id'); }
}