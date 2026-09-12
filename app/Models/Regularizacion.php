<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regularizacion extends Model
{
    protected $table = 'regularizaciones';

    protected $fillable = [
        'tramite_id', 'responsable_id', 'tipo', 'descripcion',
        'estado', 'fecha_creacion', 'fecha_regularizacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_creacion' => 'datetime',
            'fecha_regularizacion' => 'datetime',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RegularizacionDetalle::class);
    }
}