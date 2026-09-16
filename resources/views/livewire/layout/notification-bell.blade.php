<div wire:poll.15s>
    <flux:dropdown position="bottom" align="end">
        <flux:button icon="bell" variant="ghost" aria-label="Notificaciones">
            <span class="sr-only">Notificaciones</span>
            @if ($pendientes)
                <span class="absolute -top-1 -right-1 min-w-4 rounded-full bg-red-600 px-1 text-center text-[10px] text-white">{{ $pendientes }}</span>
            @endif
        </flux:button>

        <flux:menu class="w-80">
            <div class="flex items-center justify-between px-3 py-2">
                <flux:heading size="sm">Notificaciones</flux:heading>
                <flux:button size="xs" variant="ghost" wire:click="markAllAsRead">Marcar leídas</flux:button>
            </div>

            @forelse ($notificaciones as $notificacion)
                <flux:menu.item :href="route('notificaciones.abrir', $notificacion)" wire:navigate
                    @class([
                        '!items-start !whitespace-normal px-3 py-2.5',
                        '!bg-[#f2f7fa] dark:!bg-blue-950/20' => ! $notificacion->leida,
                    ])
                >
                    <div class="flex w-full flex-col gap-0.5">
                        <div class="flex items-center justify-between gap-2">
                            <span @class(['font-semibold' => ! $notificacion->leida])>{{ $notificacion->titulo }}</span>
                            @unless ($notificacion->leida)
                                <span class="size-1.5 shrink-0 rounded-full bg-red-600"></span>
                            @endunless
                        </div>
                        <span class="block text-xs text-zinc-500">{{ $notificacion->mensaje }}</span>
                    </div>
                </flux:menu.item>
            @empty
                <flux:text class="px-3 py-3">No tienes notificaciones.</flux:text>
            @endforelse
        </flux:menu>
    </flux:dropdown>
</div>
