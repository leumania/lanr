<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraDetalle extends Model
{
    protected $table = 'compra_detalles';

    protected $fillable = ['compra_id', 'item_id', 'cantidad_comprada', 'observacion'];

    protected function casts(): array
    {
        return ['cantidad_comprada' => 'decimal:2'];
    }

    public function compra(): BelongsTo { return $this->belongsTo(CompraLogistica::class, 'compra_id'); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}