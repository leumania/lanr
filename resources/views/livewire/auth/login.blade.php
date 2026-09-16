<x-layouts::auth.corporate :title="__('Log in')">
    <div class="flex flex-col gap-4">
        <div>
            <div class="text-[10px] font-bold tracking-[1.2px] text-[#b51f27]">
                {{ __('ACCESO CORPORATIVO') }}
            </div>

            <h2 class="mt-1.5 text-[27px] font-semibold text-[#142f44]">
                {{ __('Bienvenido') }}
            </h2>

            <div class="my-4.5 h-[3px] w-[38px] rounded bg-[#c91f2a]"></div>

            <p class="text-[13px] text-[#71818c]">
                {{ __('Ingrese sus credenciales.') }}
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
            @csrf

            <!-- Usuario -->
            <flux:input
                name="username"
                :label="__('Usuario')"
                :value="old('username')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="Ej: aMoreno"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Contraseña')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Ingrese su contraseña')"
                viewable
            />

            <div class="flex items-center justify-between">
                <!-- Remember Me -->
                <flux:checkbox name="remember" :label="__('Mantener sesión iniciada')" :checked="old('remember')" />

                @if (Route::has('password.request'))
                    <flux:link class="text-sm" :href="route('password.request')" wire:navigate>
                        {{ __('¿Olvidó su contraseña?') }}
                    </flux:link>
                @endif
            </div>

            <flux:button variant="primary" type="submit" class="w-full !bg-[#142f44] hover:!bg-[#0d2032]" data-test="login-button">
                {{ __('Ingresar al sistema') }}
            </flux:button>
        </form>

        <p class="text-center text-[10px] text-[#71818c]">
            LANR INVERSIONES E.I.R.L. - {{ date('Y') }}
        </p>
    </div>
</x-layouts::auth.corporate>
