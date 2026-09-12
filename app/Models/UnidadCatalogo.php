<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadCatalogo extends Model
{
    protected $table = 'unidades_catalogo';

    protected $fillable = ['abreviatura', 'uso', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}