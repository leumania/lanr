<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('workflow screens render for authenticated users', function () {
    $user = User::factory()->create();
    Role::findOrCreate('Gerencia de Obra', 'web');
    $user->assignRole('Gerencia de Obra');

    $this->actingAs($user)->get(route('tramites.create'))->assertOk();
    $this->actingAs($user)->get(route('tramites.create-sp'))->assertOk();
    $this->actingAs($user)->get(route('tramites.index'))->assertOk();
});