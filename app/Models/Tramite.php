<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tramite extends Model
{
    protected $fillable = [
        'obra_id',
        'tracking', 'tipo', 'subtipo', 'numero', 'fecha', 'proyecto', 'lugar',
        'beneficiario', 'dni_ruc', 'modalidad_pago', 'responsable', 'celular',
        'tipo_comprobante', 'banco', 'nro_comprobante', 'cuenta_cci',
        'abono', 'moneda', 'fecha_limite_pago', 'observaciones', 'prioridad', 'fecha_requerida', 'estado', 'formato', 'creador_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_requerida' => 'date',
            'fecha_limite_pago' => 'date',
            'abono' => 'decimal:2',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creador_id');
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(History::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function gestionLogistica(): HasOne
    {
        return $this->hasOne(GestionLogistica::class);
    }

    public function archivosLogistica(): HasMany
    {
        return $this->hasMany(ArchivoLogistica::class);
    }

    public function solicitudesTesoreria(): HasMany
    {
        return $this->hasMany(SolicitudTesoreria::class);
    }

    public function regularizaciones(): HasMany
    {
        return $this->hasMany(Regularizacion::class);
    }

    public function gestionSp(): HasOne
    {
        return $this->hasOne(GestionSp::class);
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function autorizacionesCompra(): HasMany
    {
        return $this->hasMany(AutorizacionCompra::class);
    }

    public function comprasLogistica(): HasMany
    {
        return $this->hasMany(CompraLogistica::class);
    }

    public function spModalidades(): HasMany { return $this->hasMany(SpModalidad::class); }
    public function spCuentas(): HasMany { return $this->hasMany(SpCuenta::class); }
    public function spComprobantes(): HasMany { return $this->hasMany(SpComprobante::class); }
}