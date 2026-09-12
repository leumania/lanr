<?php

use App\Models\Notificacion;
use Livewire\Component;

new class extends Component
{
    public function getNoLeidasProperty(): int
    {
        return Notificacion::where('usuario_id', auth()->id())
            ->where('leida', false)
            ->count();
    }

    public function getNotificacionesProperty()
    {
        return Notificacion::where('usuario_id', auth()->id())
            ->latest()
            ->limit(10)
            ->get();
    }

    public function marcarLeida(int $id): void
    {
        $notificacion = Notificacion::where('id', $id)
            ->where('usuario_id', auth()->id())
            ->first();

        if ($notificacion && ! $notificacion->leida) {
            $notificacion->update(['leida' => true]);
        }
    }

    public function marcarTodasLeidas(): void
    {
        Notificacion::where('usuario_id', auth()->id())
            ->where('leida', false)
            ->update(['leida' => true]);
    }
};
?>
<div>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" square>
            <div class="relative">
                <flux:icon.bell class="size-5" />
                @if ($this->noLeidas > 0)
                    <span class="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                        {{ $this->noLeidas > 9 ? '9+' : $this->noLeidas }}
                    </span>
                @endif
            </div>
        </flux:button>

        <flux:menu class="w-80">
            <div class="flex items-center justify-between px-3 py-2">
                <flux:heading size="sm">Notificaciones</flux:heading>
                @if ($this->noLeidas > 0)
                    <button wire:click="marcarTodasLeidas" class="text-xs text-blue-600 hover:underline dark:text-blue-400">
                        Marcar todas como leídas
                    </button>
                @endif
            </div>

            <flux:menu.separator />

            <div class="max-h-96 overflow-y-auto">
                @forelse ($this->notificaciones as $n)
                    
                        <a href="{{ $n->tramite_id ? route('tramites.show', $n->tramite_id) : '#' }}" wire:navigate wire:click="marcarLeida({{ $n->id }})" class="flex flex-col gap-0.5 border-b border-zinc-100 px-3 py-2.5 last:border-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800 {{ $n->leida ? '' : 'bg-blue-50 dark:bg-blue-950/30' }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $n->titulo }}</span>
                            @if (! $n->leida)
                                <span class="size-2 shrink-0 rounded-full bg-blue-500"></span>
                            @endif
                        </div>
                        <span class="text-xs text-zinc-500">{{ $n->mensaje }}</span>
                        <span class="text-[11px] text-zinc-400">{{ $n->created_at->diffForHumans() }}</span>
                    </a>
                @empty
                    <div class="px-3 py-6 text-center text-sm text-zinc-500">
                        No tienes notificaciones.
                    </div>
                @endforelse
            </div>
        </flux:menu>
    </flux:dropdown>
</div>