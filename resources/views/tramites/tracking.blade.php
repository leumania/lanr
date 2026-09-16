<x-layouts::app :title="__('Seguimiento ' . $tramite->tracking)">
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <div><flux:heading size="xl">Seguimiento {{ $tramite->tracking }}</flux:heading><flux:text class="text-zinc-500">Estado actual: {{ $tramite->estado }}</flux:text></div>
            <flux:button href="{{ route('tramites.show', $tramite) }}" variant="ghost" wire:navigate>Ver detalle</flux:button>
        </div>
        <flux:card>
            <flux:heading size="lg" class="mb-3">Historial del trámite</flux:heading>
            <div class="flex flex-col gap-3">
                @forelse ($tramite->history->sortByDesc('created_at') as $evento)
                    <div class="flex gap-3 border-b border-zinc-100 pb-3 last:border-0 dark:border-zinc-800">
                        <flux:icon.clock class="mt-1 size-4 shrink-0 text-zinc-400" />
                        <div><div class="font-medium">{{ $evento->accion }}</div><div class="text-sm text-zinc-500">{{ $evento->usuario?->name }} · {{ $evento->created_at?->format('d/m/Y H:i') }}</div></div>
                    </div>
                @empty
                    <flux:text class="text-zinc-500">Sin movimientos registrados.</flux:text>
                @endforelse
            </div>
        </flux:card>
    </div>
</x-layouts::app>