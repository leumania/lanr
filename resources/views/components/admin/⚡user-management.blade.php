<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('Sistemas'), 403);
    }

    public ?int $editando = null;

    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $phone = '';
    public string $cargo = '';
    public string $rol = '';

    public function getUsuariosProperty()
    {
        return User::with('roles')->orderBy('name')->get();
    }

    public function getRolesDisponiblesProperty()
    {
        return Role::orderBy('name')->pluck('name');
    }

    public function editar(int $id): void
    {
        $u = User::with('roles')->findOrFail($id);

        $this->editando = $u->id;
        $this->name = $u->name;
        $this->username = $u->username;
        $this->email = $u->email;
        $this->phone = (string) $u->phone;
        $this->cargo = (string) $u->cargo;
        $this->rol = $u->roles->first()?->name ?? '';
    }

    public function cancelar(): void
    {
        $this->reset(['editando', 'name', 'username', 'email', 'phone', 'cargo', 'rol']);
    }

    public function guardar(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username,' . $this->editando,
            'email' => 'required|email|max:255|unique:users,email,' . $this->editando,
            'phone' => 'nullable|string|max:20',
            'cargo' => 'nullable|string|max:100',
            'rol' => 'required|exists:roles,name',
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'username.required' => 'El usuario es obligatorio.',
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no es válido.',
            'email.unique' => 'Ese correo ya está en uso.',
            'rol.required' => 'Selecciona un rol.',
            'rol.exists' => 'El rol seleccionado no es válido.',
        ]);

        $u = User::findOrFail($this->editando);

        $u->update([
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'cargo' => $this->cargo,
        ]);

        $u->syncRoles([$this->rol]);

        session()->flash('status', 'Usuario actualizado correctamente.');
        $this->cancelar();
    }

    public function alternarEstado(int $id): void
    {
        $u = User::findOrFail($id);

        if ($u->id === auth()->id()) {
            session()->flash('error', 'No puedes desactivar tu propia cuenta.');
            return;
        }

        $u->update(['active' => ! $u->active]);

        session()->flash('status', $u->active ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function reiniciarClave(int $id): void
    {
        $u = User::findOrFail($id);

        $u->update([
            'password' => bcrypt('obra2026'),
            'must_change_password' => true,
        ]);

        session()->flash('status', "Contraseña de {$u->name} reiniciada a temporal (obra2026).");
    }
};
?>
<div class="space-y-3">
    <flux:heading size="xl">Administración de usuarios</flux:heading>
    <flux:text class="mb-4 text-zinc-500">Gestiona los usuarios y roles del sistema.</flux:text>

    @if (session('status'))
        <flux:callout variant="success" class="mb-3" heading="{{ session('status') }}" />
    @endif

    @if (session('error'))
        <flux:callout variant="danger" class="mb-3" heading="{{ session('error') }}" />
    @endif

    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                <tr>
                    <th class="px-3 py-3">Nombre</th>
                    <th class="px-3 py-3">Usuario</th>
                    <th class="px-3 py-3">Correo</th>
                    <th class="px-3 py-3">Rol</th>
                    <th class="px-3 py-3">Estado</th>
                    <th class="px-3 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->usuarios as $u)
                    <tr>
                        <td class="px-3 py-3 font-medium text-zinc-900 dark:text-white">{{ $u->name }}</td>
                        <td class="px-3 py-3">{{ $u->username }}</td>
                        <td class="px-3 py-3">{{ $u->email }}</td>
                        <td class="px-3 py-3">{{ $u->roles->first()?->name ?? '—' }}</td>
                        <td class="px-3 py-3">
                            @if ($u->active)
                                <flux:badge color="green" size="sm">Activo</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Inactivo</flux:badge>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" icon="pencil" wire:click="editar({{ $u->id }})">Editar</flux:button>
                                <flux:button size="sm" icon="key" wire:click="reiniciarClave({{ $u->id }})" wire:confirm="¿Reiniciar la contraseña de {{ $u->name }} a la temporal?">Reiniciar clave</flux:button>
                                <flux:button size="sm" variant="{{ $u->active ? 'danger' : 'primary' }}" wire:click="alternarEstado({{ $u->id }})" wire:confirm="¿Confirmas {{ $u->active ? 'desactivar' : 'activar' }} a {{ $u->name }}?">
                                    {{ $u->active ? 'Desactivar' : 'Activar' }}
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($editando)
        <flux:modal wire:model="editando" name="editar-usuario" class="max-w-lg">
            <form wire:submit="guardar" class="flex flex-col gap-3">
                <flux:heading size="lg">Editar usuario</flux:heading>

                <flux:input label="Nombre completo" wire:model="name" />
                <flux:input label="Usuario" wire:model="username" />
                <flux:input label="Correo electrónico" type="email" wire:model="email" />
                <flux:input label="Teléfono" wire:model="phone" />
                <flux:input label="Cargo" wire:model="cargo" />

                <flux:select label="Rol" wire:model="rol">
                    <flux:select.option value="">Selecciona un rol</flux:select.option>
                    @foreach ($this->rolesDisponibles as $r)
                        <flux:select.option value="{{ $r }}">{{ $r }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="cancelar">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary">Guardar cambios</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>