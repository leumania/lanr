<?php

namespace App\Livewire\Admin;

use App\Models\Obra;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class UserManagement extends Component
{
    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $cargo = '';

    public string $role = '';

    public string $password = '';

    public ?int $editingId = null;

    public array $obraIds = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('Sistemas'), 403);
        $this->role = Role::query()->orderBy('name')->value('name') ?? 'Sistemas';
    }

    public function save(): void
    {
        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;

        $this->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]{3,30}$/', Rule::unique('users', 'username')->ignore($this->editingId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'cargo' => 'nullable|string|max:150',
            'role' => 'required|string|exists:roles,name',
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', Password::default()],
            'obraIds' => 'array',
            'obraIds.*' => 'integer|exists:obras,id',
        ], [
            'username.regex' => 'El usuario debe tener 3 a 30 caracteres: letras, números, puntos, guiones o guion bajo.',
        ]);

        if ($this->password !== '' && $this->editingId && Hash::check($this->password, $user->password)) {
            $this->addError('password', 'La nueva contraseña debe ser distinta de la actual.');

            return;
        }

        $esNuevaContrasenaParaOtro = $this->password !== '' && $this->editingId && $user->id !== auth()->id();

        $user->fill([
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email ?: null,
            'cargo' => $this->cargo ?: null,
            'active' => true,
        ]);
        if ($this->password !== '') {
            $user->password = Hash::make($this->password);
        }
        if ($esNuevaContrasenaParaOtro) {
            $user->must_change_password = true;
        }
        $user->save();
        $user->syncRoles([$this->role]);
        $user->obras()->syncWithPivotValues($this->obraIds, ['active' => true]);
        if ($this->obraIds !== [] && ! in_array($user->obra_activa_id, $this->obraIds, true)) {
            $user->update(['obra_activa_id' => $this->obraIds[0]]);
        }

        $this->reset(['name', 'username', 'email', 'cargo', 'password', 'editingId', 'obraIds']);
        session()->flash('status', 'Usuario guardado correctamente.');
    }

    public function edit(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email ?? '';
        $this->cargo = $user->cargo ?? '';
        $this->role = $user->getRoleNames()->first() ?? 'Usuario';
        $this->obraIds = $user->obras()->pluck('obras.id')->map(fn ($id): int => (int) $id)->all();
        if ($this->obraIds === [] && $user->obra_activa_id) {
            $this->obraIds = [(int) $user->obra_activa_id];
        }
    }

    public function toggle(int $userId): void
    {
        $user = User::findOrFail($userId);
        abort_if($user->id === auth()->id(), 422, 'No puedes desactivar tu propio usuario.');
        $user->update(['active' => ! $user->active]);
    }

    public function render()
    {
        return view('livewire.admin.user-management', [
            'users' => User::with('roles')->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->pluck('name'),
            'obras' => Obra::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }
}
