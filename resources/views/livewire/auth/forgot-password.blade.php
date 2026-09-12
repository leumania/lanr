<x-layouts::auth.corporate :title="__('Forgot password')">
    <div class="flex flex-col gap-6">
        <div>
            <div class="text-[10px] font-bold tracking-[1.2px] text-[#b51f27]">
                {{ __('ACCESO CORPORATIVO') }}
            </div>

            <h2 class="mt-1.5 text-[27px] font-semibold text-[#142f44]">
                {{ __('¿Olvidó su contraseña?') }}
            </h2>

            <div class="my-4.5 h-[3px] w-[38px] rounded bg-[#c91f2a]"></div>

            <p class="text-[13px] text-[#71818c]">
                {{ __('Ingrese su correo y le enviaremos un enlace para restablecer su contraseña.') }}
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Correo electrónico')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />

            <flux:button variant="primary" type="submit" class="w-full !bg-[#142f44] hover:!bg-[#0d2032]" data-test="email-password-reset-link-button">
                {{ __('Enviar enlace de restablecimiento') }}
            </flux:button>
        </form>

        <div class="text-center text-sm text-zinc-500">
            <span>{{ __('¿Ya la recordó?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Iniciar sesión') }}</flux:link>
        </div>
    </div>
</x-layouts::auth.corporate>
