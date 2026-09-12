<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    protected $fillable = [
        'tramite_id', 'rol', 'usuario_id', 'aprobado', 'fecha_aprobacion',
    ];

    protected function casts(): array
    {
        return [
            'aprobado' => 'boolean',
            'fecha_aprobacion' => 'datetime',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}