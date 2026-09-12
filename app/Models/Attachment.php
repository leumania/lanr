<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = ['tramite_id', 'nombre_original', 'nombre_archivo', 'orden'];

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }
}