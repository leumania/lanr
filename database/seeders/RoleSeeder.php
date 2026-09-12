<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Gerencia de Obra',
            'Control y Planeamiento',
            'Gerencia General',
            'Administración',
            'Logística',
            'Tesorería',
            'Sistemas',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Asignar cada usuario a su rol según su cargo
        $map = [
            'aMoreno'   => 'Gerencia de Obra',
            'jBecerra'  => 'Control y Planeamiento',
            'lNeyra'    => 'Gerencia General',
            'sSanchez'  => 'Administración',
            'rVasquez'  => 'Logística',
            'yCoronado' => 'Tesorería',
            'oGonzales' => 'Sistemas',
        ];

        foreach ($map as $username => $roleName) {
            $user = User::where('username', $username)->first();
            if ($user) {
                $user->syncRoles([$roleName]);
            }
        }
    }
}