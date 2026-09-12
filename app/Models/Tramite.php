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

    /**
     * Un trámite en 'Pendiente de mi revisión' solo lo ve su creador; un REQ en
     * 'Pendiente de aprobación' solo lo ven el creador y los aprobadores asignados
     * (approvals). Cualquier otro estado es visible para toda la obra.
     */
    public function scopeVisibleParaUsuario($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('creador_id', $user->id)
                ->orWhere(function ($sub) use ($user) {
                    $sub->where('estado', '!=', 'Pendiente de mi revisión')
                        ->where(function ($restriccion) use ($user) {
                            $restriccion->where(function ($noEsReqPendienteAprobacion) {
                                $noEsReqPendienteAprobacion->where('tipo', '!=', 'REQ')
                                    ->orWhere('estado', '!=', 'Pendiente de aprobación');
                            })->orWhereHas('approvals', fn ($a) => $a->where('usuario_id', $user->id));
                        });
                });
        });
    }

    public function esVisiblePara(User $user): bool
    {
        if ($this->estado === 'Pendiente de mi revisión') {
            return $this->creador_id === $user->id;
        }

        if ($this->tipo === 'REQ' && $this->estado === 'Pendiente de aprobación') {
            return $this->creador_id === $user->id
                || $this->approvals()->where('usuario_id', $user->id)->exists();
        }

        return true;
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

    public function spModalidades(): HasMany
    {
        return $this->hasMany(SpModalidad::class);
    }

    public function spCuentas(): HasMany
    {
        return $this->hasMany(SpCuenta::class);
    }

    public function spComprobantes(): HasMany
    {
        return $this->hasMany(SpComprobante::class);
    }
}
