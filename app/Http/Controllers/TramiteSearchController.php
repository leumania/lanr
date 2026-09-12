<?php

namespace App\Http\Controllers;

use App\Models\Tramite;
use Illuminate\Http\Request;

class TramiteSearchController
{
    public function __invoke(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        abort_if(mb_strlen($term) < 2, 422, 'La búsqueda debe tener al menos 2 caracteres.');

        $tramites = Tramite::query()
            ->where('obra_id', $request->user()->obra_activa_id)
            ->where(function ($query) use ($term) {
                $query->where('tracking', 'like', "%{$term}%")
                    ->orWhere('numero', 'like', "%{$term}%")
                    ->orWhere('beneficiario', 'like', "%{$term}%")
                    ->orWhere('tipo', 'like', "%{$term}%");
            })
            ->visibleParaUsuario($request->user())
            ->with('creador:id,name')
            ->latest()
            ->limit(25)
            ->get(['id', 'tracking', 'numero', 'tipo', 'fecha', 'estado', 'creador_id']);

        return response()->json($tramites->map(fn (Tramite $tramite) => [
            'id' => $tramite->id,
            'tracking' => $tramite->tracking,
            'numero' => $tramite->numero,
            'tipo' => $tramite->tipo,
            'fecha' => $tramite->fecha?->toDateString(),
            'estado' => $tramite->estado,
            'creador' => $tramite->creador?->name,
            'url' => route('tramites.show', $tramite),
        ]));
    }
}