<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#f5f7f9] dark:bg-zinc-900">
        <flux:sidebar sticky collapsible="mobile" class="w-60! border-e border-black/10 bg-gradient-to-b from-[#10283c] to-[#183950] text-white">
            <flux:sidebar.header class="border-b border-white/10 pb-3">
                <div class="flex items-center gap-2.5 px-1">
                    <img src="{{ asset('images/logo-lanr.jpeg') }}" alt="LANR Inversiones" class="h-9 w-9 rounded-lg object-cover" />
                    <div class="flex flex-col leading-tight">
                        <span class="text-sm font-bold text-white">LANR INVERSIONES</span>
                        <span class="text-[11px] text-[#b9c8d2]">Sistema Interno de Gestión</span>
                    </div>
                </div>
                <flux:sidebar.collapse class="text-white hover:bg-white/10 lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group class="grid [&_[data-flux-sidebar-item]]:text-[#d8e2e9] [&_[data-flux-sidebar-item]]:hover:bg-white/10 [&_[data-flux-sidebar-item]]:hover:text-white [&_[data-flux-sidebar-item][data-current]]:bg-white/15 [&_[data-flux-sidebar-item][data-current]]:text-white [&_svg]:text-[#8fa4b3]">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Inicio') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="folder" :href="route('tramites.index')" :current="request()->routeIs('tramites.index')" wire:navigate>
                        {{ __('Trámites') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="plus-circle" :href="route('tramites.create')" :current="request()->routeIs('tramites.create')" wire:navigate>
                        {{ __('Nuevo trámite') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Gestión')" class="mt-4 grid [&_[data-flux-sidebar-heading]]:text-[#8fa4b3] [&_[data-flux-sidebar-item]]:text-[#d8e2e9] [&_[data-flux-sidebar-item]]:hover:bg-white/10 [&_[data-flux-sidebar-item]]:hover:text-white [&_[data-flux-sidebar-item][data-current]]:bg-white/15 [&_[data-flux-sidebar-item][data-current]]:text-white [&_svg]:text-[#8fa4b3]">
                    <flux:sidebar.item icon="receipt-percent" :href="route('admin.reimbursements')" :current="request()->routeIs('admin.reimbursements')" wire:navigate>
                        {{ __('Reembolsos / Rendiciones') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.orders')" :current="request()->routeIs('admin.orders')" wire:navigate>
                        {{ __('Órdenes') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="user" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>
                        {{ __('Mi perfil') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="question-mark-circle" :href="route('help')" :current="request()->routeIs('help')" wire:navigate>
                        {{ __('Ayuda del sistema') }}
                    </flux:sidebar.item>

                    @if (auth()->user()->hasAnyRole(['Logística', 'Sistemas']))
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('admin.regularizations')" :current="request()->routeIs('admin.regularizations')" wire:navigate>
                        {{ __('Regularizaciones') }}
                    </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasAnyRole(['Tesorería', 'Sistemas']))
                    <flux:sidebar.item icon="banknotes" :href="route('admin.treasury')" :current="request()->routeIs('admin.treasury')" wire:navigate>
                        {{ __('Tesorería') }}
                    </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasRole('Sistemas'))
                    <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>
                        {{ __('Usuarios') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="adjustments-horizontal" :href="route('admin.units')" :current="request()->routeIs('admin.units')" wire:navigate>
                        {{ __('Unidades') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="banknotes" :href="route('admin.currencies')" :current="request()->routeIs('admin.currencies')" wire:navigate>
                        {{ __('Monedas') }}
                    </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasAnyRole(['Gerencia General', 'Sistemas', 'Gerencia de Obra']))
                    <flux:sidebar.item icon="building-office" :href="route('admin.obras')" :current="request()->routeIs('admin.obras')" wire:navigate>
                        {{ __('Obras') }}
                    </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />
        </flux:sidebar>

        <!-- Top Header -->
        <flux:header class="min-h-12! border-b border-zinc-200 bg-white px-3! lg:px-4! dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <div class="hidden items-center gap-2 lg:flex">
                <flux:icon.building-office-2 class="size-5 text-[#8fa4b3]" />
                <livewire:obras.selector />
            </div>

            <flux:spacer />

            <div class="flex items-center gap-3 sm:gap-3">
                <div x-data>
                    <flux:button
                        x-show="!$flux.dark"
                        x-cloak
                        @click="$flux.appearance = 'dark'"
                        variant="ghost"
                        icon="moon"
                        title="{{ __('Activar tema oscuro') }}"
                    />
                    <flux:button
                        x-show="$flux.dark"
                        x-cloak
                        @click="$flux.appearance = 'light'"
                        variant="ghost"
                        icon="sun"
                        title="{{ __('Activar tema claro') }}"
                    />
                </div>

                <livewire:layout.notification-bell />

                <div class="flex items-center gap-2 border-s border-zinc-200 ps-3 sm:ps-4 dark:border-zinc-800">
                    <flux:avatar :initials="auth()->user()->initials()" size="sm" circle />
                    <div class="hidden leading-tight sm:block">
                        <div class="text-sm font-semibold text-[#142f44] dark:text-white">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->cargo }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:button
                        type="submit"
                        variant="ghost"
                        icon="arrow-right-start-on-rectangle"
                        data-test="logout-button"
                    >
                        <span class="hidden sm:inline">{{ __('Salir') }}</span>
                    </flux:button>
                </form>
            </div>
        </flux:header>

        {{ $slot }}

        <footer class="flex h-[38px] items-center justify-center border-t border-[#dde5ea] bg-white text-[10px] text-[#7d8b95] [grid-column:1/-1] dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-500">
            LANR INVERSIONES E.I.R.L. &middot; RUC 20609096838 &middot; Sistema Interno &middot; {{ date('Y') }}
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
