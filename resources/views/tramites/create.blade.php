@php
    $user = auth()->user();

    $modulos = [
        [
            'href' => route('tramites.create-requirement'),
            'icon' => 'archive-box',
            'titulo' => 'Requerimiento de materiales',
            'descripcion' => 'Materiales de obra, seguridad y útiles de oficina según su rol.',
            'visible' => $user->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Administración', 'Logística', 'Tesorería', 'Sistemas']),
        ],
        [
            'href' => route('tramites.create-sp'),
            'icon' => 'credit-card',
            'titulo' => 'Solicitud de pago',
            'descripcion' => 'Registra una solicitud y envíala a la revisión correspondiente.',
            'visible' => $user->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Contabilidad', 'Administración']),
        ],
        [
            'href' => route('admin.orders'),
            'icon' => 'pencil-square',
            'titulo' => 'Orden',
            'descripcion' => 'Orden de servicio u orden de compra. Registro base funcional.',
            'visible' => $user->hasAnyRole(['Gerencia de Obra', 'Administración', 'Sistemas']),
        ],
        [
            'href' => route('admin.reimbursements'),
            'icon' => 'banknotes',
            'titulo' => 'Reembolso / Rendición',
            'descripcion' => 'Gastos con dinero personal o fondos entregados por la empresa.',
            'visible' => $user->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Administración', 'Logística', 'Tesorería', 'Contabilidad', 'Sistemas']),
        ],
    ];
@endphp

<x-layouts::app :title="__('Nuevo trámite')">
    <div class="space-y-4">
        <div>
            <flux:heading size="xl" class="text-[#142f44]">Nuevo trámite</flux:heading>
            <flux:text class="mt-1 text-zinc-500">Seleccione el proceso que va a registrar. Cada trámite tendrá su propia pantalla y flujo.</flux:text>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($modulos as $modulo)
                @continue(! $modulo['visible'])

                <a
                    href="{{ $modulo['href'] }}"
                    wire:navigate
                    class="group flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-[#142f44]/30 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
                >
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400">
                        <flux:icon :icon="$modulo['icon']" class="size-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-[#142f44] dark:text-white">{{ $modulo['titulo'] }}</div>
                        <div class="mt-0.5 text-sm text-zinc-500">{{ $modulo['descripcion'] }}</div>
                    </div>

                    <flux:icon.arrow-right class="size-4 shrink-0 text-zinc-400 transition group-hover:translate-x-0.5 group-hover:text-[#142f44] dark:group-hover:text-white" />
                </a>
            @endforeach
        </div>
    </div>
</x-layouts::app>
