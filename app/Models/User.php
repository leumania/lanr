<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'cargo',
        'phone',
        'profile_photo',
        'must_change_password',
        'active',
        'obra_activa_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'active' => 'boolean',
        ];
    }
    public function initials(): string
    {
        return \Illuminate\Support\Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => \Illuminate\Support\Str::substr($name, 0, 1))
            ->implode('');
    }

    public function obraActiva(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_activa_id');
    }

    public function obras(): BelongsToMany
    {
        return $this->belongsToMany(Obra::class)->wherePivot('active', true);
    }

    public function hasAccessToObra(?int $obraId): bool
    {
        return $obraId !== null && (
            (int) $this->obra_activa_id === $obraId
            || $this->obras()->whereKey($obraId)->exists()
        );
    }

    public function accessibleObras()
    {
        return Obra::query()
            ->where('activa', true)
            ->where(function ($query) {
                $query->whereKey($this->obra_activa_id)
                    ->orWhereHas('usuariosAsignados', fn ($assigned) => $assigned->whereKey($this->id));
            });
    }
}