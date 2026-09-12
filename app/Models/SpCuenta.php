<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpCuenta extends Model
{
    protected $table = 'sp_cuentas';
    protected $fillable = ['tramite_id', 'banco', 'cuenta_cci'];
    public function tramite(): BelongsTo { return $this->belongsTo(Tramite::class); }
}