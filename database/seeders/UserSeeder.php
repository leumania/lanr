<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Artidoro Moreno', 'username' => 'aMoreno', 'cargo' => 'Gerencia de Obra'],
            ['name' => 'José Luis Becerra', 'username' => 'jBecerra', 'cargo' => 'Control y Planeamiento'],
            ['name' => 'Luis Neyra', 'username' => 'lNeyra', 'cargo' => 'Gerencia General'],
            ['name' => 'Sara Sanchez', 'username' => 'sSanchez', 'cargo' => 'Administración'],
            ['name' => 'Rodrigo Vasquez', 'username' => 'rVasquez', 'cargo' => 'Logística'],
            ['name' => 'Yoana Coronado', 'username' => 'yCoronado', 'cargo' => 'Tesorería'],
            ['name' => 'Olenka Gonzales', 'username' => 'oGonzales', 'cargo' => 'Sistemas'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['username' => $u['username']],
                [
                    'name' => $u['name'],
                    'email' => strtolower($u['username']) . '@lanr.local',
                    'cargo' => $u['cargo'],
                    'password' => Hash::make('obra2026'),
                    'must_change_password' => true,
                    'active' => true,
                ]
            );
        }
    }
}