<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\Obra;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
        $this->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($this->editingId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'cargo' => 'nullable|string|max:150',
            'role' => 'required|string|exists:roles,name',
            'password' => $this->editingId ? 'nullable|string|min:8' : 'required|string|min:8',
            'obraIds' => 'array',
            'obraIds.*' => 'integer|exists:obras,id',
        ]);

        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;
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