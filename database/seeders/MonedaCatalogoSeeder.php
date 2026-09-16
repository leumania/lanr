<?php

namespace Database\Seeders;

use App\Models\MonedaCatalogo;
use Illuminate\Database\Seeder;

class MonedaCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $monedas = [
            ['codigo' => 'PEN', 'nombre' => 'Soles', 'simbolo' => 'S/'],
            ['codigo' => 'USD', 'nombre' => 'Dólares', 'simbolo' => 'US$'],
        ];

        foreach ($monedas as $moneda) {
            MonedaCatalogo::firstOrCreate(
                ['codigo' => $moneda['codigo']],
                ['nombre' => $moneda['nombre'], 'simbolo' => $moneda['simbolo'], 'active' => true]
            );
        }
    }
}
