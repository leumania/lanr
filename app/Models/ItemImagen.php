<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemImagen extends Model
{
    protected $table = 'item_imagenes';

    protected $fillable = ['item_id', 'nombre_original', 'nombre_archivo'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}