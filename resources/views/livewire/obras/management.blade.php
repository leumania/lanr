<div class="mx-auto max-w-5xl space-y-4 p-4">
    <div>
        <flux:heading size="xl">Obras</flux:heading>
        <flux:text class="mt-1 text-zinc-500">Registra y administra las obras disponibles para el sistema.</flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success" heading="{{ session('status') }}" />
    @endif

    <flux:card>
        <flux:heading size="lg" class="mb-3">Registrar nueva obra</flux:heading>
        <form wire:submit="save" class="grid gap-3 sm:grid-cols-2">
            <flux:input label="Nombre" wire:model="nombre" />
            <flux:input label="Código" wire:model="codigo" placeholder="Ej: PROYECTO01" />
            <flux:textarea label="Proyecto" wire:model="proyecto" rows="2" />
            <flux:textarea label="Lugar" wire:model="lugar" rows="2" />
            <div class="sm:col-span-2"><flux:textarea label="Descripción" wire:model="descripcion" rows="2" /></div>
            <div class="sm:col-span-2 flex justify-end"><flux:button type="submit" variant="primary">Registrar obra</flux:button></div>
        </form>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-3">Obras registradas</flux:heading>
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($obras as $obra)
                <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div><div class="font-medium">{{ $obra->nombre }} <span class="text-xs text-zinc-500">({{ $obra->codigo }})</span></div><div class="text-sm text-zinc-500">{{ $obra->lugar ?: 'Sin lugar configurado' }}</div></div>
                    <flux:button size="sm" variant="ghost" wire:click="toggle({{ $obra->id }})">{{ $obra->activa ? 'Desactivar' : 'Activar' }}</flux:button>
                </div>
            @endforeach
        </div>
    </flux:card>
</div>
