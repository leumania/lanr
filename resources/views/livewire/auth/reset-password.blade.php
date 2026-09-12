<x-layouts::auth.corporate :title="__('Reset password')">
    <div class="flex flex-col gap-6">
        <div>
            <div class="text-[10px] font-bold tracking-[1.2px] text-[#b51f27]">
                {{ __('ACCESO CORPORATIVO') }}
            </div>

            <h2 class="mt-1.5 text-[27px] font-semibold text-[#142f44]">
                {{ __('Restablecer contraseña') }}
            </h2>

            <div class="my-4.5 h-[3px] w-[38px] rounded bg-[#c91f2a]"></div>

            <p class="text-[13px] text-[#71818c]">
                {{ __('Ingrese su nueva contraseña a continuación.') }}
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <flux:input
                name="email"
                value="{{ request('email') }}"
                :label="__('Correo electrónico')"
                type="email"
                required
                autocomplete="email"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Nueva contraseña')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Contraseña')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirmar contraseña')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirmar contraseña')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:button type="submit" variant="primary" class="w-full !bg-[#142f44] hover:!bg-[#0d2032]" data-test="reset-password-button">
                {{ __('Restablecer contraseña') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth.corporate>
