<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonedaCatalogo extends Model
{
    protected $table = 'monedas_catalogo';

    protected $fillable = ['codigo', 'nombre', 'simbolo', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
