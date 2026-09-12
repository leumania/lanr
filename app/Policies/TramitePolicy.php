<?php

namespace App\Policies;

use App\Models\Tramite;
use App\Models\User;

class TramitePolicy
{
    public function view(User $user, Tramite $tramite): bool
    {
        if (! $user->obra_activa_id || $tramite->obra_id !== $user->obra_activa_id) {
            return false;
        }

        return $tramite->estado !== 'Pendiente de mi revisión'
            || $tramite->creador_id === $user->id;
    }
}