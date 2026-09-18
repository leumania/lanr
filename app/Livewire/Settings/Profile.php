<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public $photo;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = Auth::user()->phone ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate(array_merge($this->profileRules($user->id), [
            'phone' => 'nullable|string|max:30|regex:/^\d{7,15}$/',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]), [
            'phone.regex' => 'El teléfono debe contener solo números, entre 7 y 15 dígitos.',
        ]);

        $user->fill(['name' => $validated['name'], 'email' => $validated['email'], 'phone' => $validated['phone'] ?? null]);

        if ($this->photo) {
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $user->profile_photo = $this->photo->store('profile-photos', 'public');
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $this->photo = null;

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function removePhoto(): void
    {
        $user = Auth::user();

        if ($user->profile_photo) {
            Storage::disk('public')->delete($user->profile_photo);
            $user->update(['profile_photo' => null]);
        }

        $this->photo = null;

        Flux::toast(variant: 'success', text: __('Foto de perfil eliminada.'));
    }
}
