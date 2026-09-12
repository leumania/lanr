<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpModalidad extends Model
{
    protected $table = 'sp_modalidades';
    protected $fillable = ['tramite_id', 'nombre', 'monto'];
    protected function casts(): array { return ['monto' => 'decimal:2']; }
    public function tramite(): BelongsTo { return $this->belongsTo(Tramite::class); }
}