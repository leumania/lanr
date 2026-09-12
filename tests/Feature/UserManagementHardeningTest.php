<?php

use App\Livewire\Admin\UserManagement;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('username must match the allowed charset', function () {
    Role::findOrCreate('Sistemas', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Sistemas');
    $this->actingAs($admin);

    Livewire::test(UserManagement::class)
        ->set('name', 'Nuevo Usuario')
        ->set('username', 'usuario inválido!')
        ->set('email', 'nuevo@example.com')
        ->set('role', 'Sistemas')
        ->set('password', 'Str0ng#Pass1')
        ->call('save')
        ->assertHasErrors(['username']);
});

test('resetting another users password forces a change on next login and rejects reusing the same password', function () {
    Role::findOrCreate('Sistemas', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Sistemas');

    $target = User::factory()->create(['must_change_password' => false, 'password' => Hash::make('OldPass#123')]);
    $target->assignRole('Sistemas');

    $this->actingAs($admin);

    Livewire::test(UserManagement::class)
        ->call('edit', $target->id)
        ->set('password', 'OldPass#123')
        ->call('save')
        ->assertHasErrors(['password']);

    expect($target->fresh()->must_change_password)->toBeFalse();

    Livewire::test(UserManagement::class)
        ->call('edit', $target->id)
        ->set('password', 'BrandNew#456')
        ->call('save')
        ->assertHasNoErrors();

    $target->refresh();
    expect(Hash::check('BrandNew#456', $target->password))->toBeTrue();
    expect($target->must_change_password)->toBeTrue();
});
