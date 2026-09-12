<div class="leading-tight">
    <flux:label class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">Obra activa</flux:label>
    <flux:select
        wire:model.live="obraId"
        size="sm"
        class="mt-0.5 min-w-[9rem] border-0 bg-transparent p-0 font-semibold text-[#142f44] shadow-none"
    >
        @foreach ($obras as $obra)
            <flux:select.option value="{{ $obra->id }}">{{ $obra->nombre }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
