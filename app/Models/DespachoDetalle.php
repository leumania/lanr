<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DespachoDetalle extends Model
{
    protected $table = 'despacho_detalle';

    protected $fillable = ['despacho_id', 'item_id', 'cantidad_despachada'];

    protected function casts(): array
    {
        return ['cantidad_despachada' => 'decimal:2'];
    }

    public function despacho(): BelongsTo
    {
        return $this->belongsTo(Despacho::class, 'despacho_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
