<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->after(function ($validator) use ($user, $input) {
            if (isset($input['password']) && Hash::check($input['password'], $user->password)) {
                $validator->errors()->add('password', 'La nueva contraseña debe ser diferente a la contraseña actual.');
            }
        })->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
