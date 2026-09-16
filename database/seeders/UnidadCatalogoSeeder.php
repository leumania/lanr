<?php

namespace Database\Seeders;

use App\Models\UnidadCatalogo;
use Illuminate\Database\Seeder;

class UnidadCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [
            ['abreviatura' => 'BALDE', 'uso' => 'REQ'],
            ['abreviatura' => 'BLS', 'uso' => 'REQ'],
            ['abreviatura' => 'CAJA', 'uso' => 'REQ'],
            ['abreviatura' => 'DOC', 'uso' => 'REQ'],
            ['abreviatura' => 'GLB', 'uso' => 'REQ'],
            ['abreviatura' => 'GLN', 'uso' => 'REQ'],
            ['abreviatura' => 'HOJAS', 'uso' => 'REQ'],
            ['abreviatura' => 'JUEGO', 'uso' => 'REQ'],
            ['abreviatura' => 'KG', 'uso' => 'AMBOS'],
            ['abreviatura' => 'M', 'uso' => 'REQ'],
            ['abreviatura' => 'PAQ', 'uso' => 'REQ'],
            ['abreviatura' => 'PLAN', 'uso' => 'REQ'],
            ['abreviatura' => 'ROLLO', 'uso' => 'REQ'],
            ['abreviatura' => 'UND', 'uso' => 'AMBOS'],
            ['abreviatura' => 'VARILLAS', 'uso' => 'REQ'],
            ['abreviatura' => 'GLB', 'uso' => 'SP'],
            ['abreviatura' => 'DIA', 'uso' => 'SP'],
            ['abreviatura' => 'HE', 'uso' => 'SP'],
            ['abreviatura' => 'GAL', 'uso' => 'SP'],
            ['abreviatura' => 'HM', 'uso' => 'SP'],
            ['abreviatura' => 'PTO', 'uso' => 'SP'],
            ['abreviatura' => 'MES', 'uso' => 'SP'],
            ['abreviatura' => 'M3', 'uso' => 'SP'],
        ];

        foreach ($unidades as $unidad) {
            UnidadCatalogo::firstOrCreate(
                ['abreviatura' => $unidad['abreviatura'], 'uso' => $unidad['uso']],
                ['active' => true]
            );
        }
    }
}
