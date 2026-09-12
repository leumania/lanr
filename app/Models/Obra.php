<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Obra extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'codigo', 'proyecto', 'lugar', 'descripcion', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function tramites(): HasMany
    {
        return $this->hasMany(Tramite::class);
    }

    public function usuariosActivos(): HasMany
    {
        return $this->hasMany(User::class, 'obra_activa_id');
    }

    public function usuariosAsignados(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->wherePivot('active', true);
    }
}
