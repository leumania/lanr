<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpComprobante extends Model
{
    protected $table = 'sp_comprobantes';
    protected $fillable = ['tramite_id', 'tipo', 'numero', 'monto'];
    protected function casts(): array { return ['monto' => 'decimal:2']; }
    public function tramite(): BelongsTo { return $this->belongsTo(Tramite::class); }
}