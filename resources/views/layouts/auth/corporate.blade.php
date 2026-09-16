<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#f5f7f9] antialiased">
        <div class="grid min-h-svh lg:grid-cols-[1.05fr_0.95fr]">
            <section class="hidden flex-col justify-between bg-gradient-to-br from-[#10283c] to-[#183950] px-14 py-12 text-white lg:flex">
                <div class="flex items-center gap-3.5 border-b border-white/10 pb-4">
                    <img src="{{ asset('images/logo-lanr.jpeg') }}" alt="LANR Inversiones" class="h-[68px] w-[68px] rounded-xl object-cover" />
                    <div>
                        <strong class="block text-base tracking-wide">LANR INVERSIONES</strong>
                        <span class="mt-1 block text-[11px] text-[#b9c8d2]">Sistema Interno de Gestión</span>
                    </div>
                </div>

                <div class="max-w-[540px]">
                    <span class="mb-3 inline-block rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-[11px] text-[#d8e2e9]">
                        Plataforma corporativa
                    </span>

                    <h1 class="mb-3 text-[40px] leading-[1.15] font-bold tracking-tight text-white">
                        Gestión integrada para los procesos de LANR Inversiones
                    </h1>

                    <p class="max-w-[500px] text-sm leading-relaxed text-[#becad3]">
                        Sistema interno para el control integral de la obra, centralizando la gestión administrativa, operativa y el seguimiento de los procesos de LANR Inversiones.
                    </p>
                </div>

                <div>
                    <div class="grid grid-cols-[1.5fr_0.65fr_1fr] gap-7 border-t border-white/10 pt-4">
                        <div>
                            <span class="mb-1 block text-[10px] text-[#8fa4b3]">Razón Social</span>
                            <strong class="block text-[11px] leading-snug text-white">LANR INVERSIONES E.I.R.L.</strong>
                        </div>
                        <div>
                            <span class="mb-1 block text-[10px] text-[#8fa4b3]">RUC</span>
                            <strong class="block text-[11px] leading-snug text-white">20609096838</strong>
                        </div>
                        <div>
                            <span class="mb-1 block text-[10px] text-[#8fa4b3]">Sede</span>
                            <strong class="block text-[11px] leading-snug text-white">Jaén - Cajamarca</strong>
                        </div>
                    </div>

                    <div class="mt-4.5 text-[10px] text-[#91a6b4]">Uso exclusivo para personal autorizado</div>
                </div>
            </section>

            <section class="grid place-items-center p-7">
                <div class="w-full max-w-[430px] rounded-2xl border border-[#dde5ea] bg-white p-6 shadow-[0_12px_35px_rgba(19,48,69,0.06)]">
                    {{ $slot }}
                </div>
            </section>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
