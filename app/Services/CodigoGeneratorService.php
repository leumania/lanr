<?php

namespace App\Services;

use App\Models\Tramite;

class CodigoGeneratorService
{
    public function nextTracking(string $tipo, string $year): string
    {
        $prefijo = $tipo === 'REQ' ? 'REQ' : 'SP';
        $patron = "{$prefijo}-LAVEGA-{$year}-%";

        $ultimo = Tramite::where('tracking', 'like', $patron)
            ->orderByDesc('id')
            ->first();

        $siguiente = 1;

        if ($ultimo) {
            $partes = explode('-', $ultimo->tracking);
            $siguiente = (int) end($partes) + 1;
        }

        return sprintf('%s-LAVEGA-%s-%03d', $prefijo, $year, $siguiente);
    }

    public function formatoF01A(string $numero): string
    {
        $primero = preg_replace('/\D/', '', explode('-', $numero)[0] ?? '');
        $primero = $primero !== '' ? $primero : (explode('-', $numero)[0] ?? '');

        return "F01A-LANR-{$primero}";
    }
}