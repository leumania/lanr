<x-layouts::app :title="__('Buscar trámite')">
    <div class="space-y-3">
        <div>
            <flux:heading size="xl" class="text-[#142f44]">Buscar trámite</flux:heading>
            <flux:text class="mt-1 text-zinc-500">Localiza requerimientos y solicitudes de pago por su código.</flux:text>
        </div>

        <flux:card
            x-data="buscarTramites('{{ route('tramites.search.api') }}')"
            x-init="init()"
            class="p-3"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg" class="text-[#142f44]">Consulta de trámites</flux:heading>
                    <flux:text class="mt-1 text-zinc-500">Seleccione el tipo de trámite y empiece a escribir el código.</flux:text>
                </div>
                <flux:icon.magnifying-glass class="size-6 shrink-0 text-zinc-300" />
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,220px)_1fr]">
                <flux:select label="Tipo de trámite" x-model="tipo" @change="buscar()">
                    <flux:select.option value="REQ">Requerimiento de materiales</flux:select.option>
                    <flux:select.option value="SP">Solicitud de pago</flux:select.option>
                </flux:select>

                <div>
                    <flux:label>Código</flux:label>
                    <flux:input
                        x-model="codigo"
                        @input.debounce.350ms="buscar()"
                        icon="magnifying-glass"
                        autocomplete="off"
                        placeholder="Escriba parte del código..."
                    />
                    <flux:text size="sm" class="mt-1 text-zinc-400">REQ-LAVEGA-2026-001 / SP-LAVEGA-2026-001</flux:text>
                </div>
            </div>

            <div class="mt-4">
                <template x-if="codigo.trim().length < 2">
                    <div class="flex flex-col items-center gap-1 rounded-lg border border-dashed border-zinc-200 py-8 text-center dark:border-zinc-700">
                        <flux:icon.pencil class="size-5 text-zinc-300" />
                        <flux:text class="font-medium text-zinc-600 dark:text-zinc-300">Empieza a escribir</flux:text>
                        <flux:text size="sm" class="text-zinc-400">Los trámites coincidentes aparecerán aquí automáticamente.</flux:text>
                    </div>
                </template>

                <template x-if="codigo.trim().length >= 2 && cargando">
                    <flux:text class="py-4 text-center text-zinc-400">Buscando...</flux:text>
                </template>

                <template x-if="codigo.trim().length >= 2 && !cargando && error">
                    <flux:text class="py-4 text-center text-red-500" x-text="error"></flux:text>
                </template>

                <template x-if="codigo.trim().length >= 2 && !cargando && !error && resultados.length === 0">
                    <flux:text class="py-4 text-center text-zinc-400">No se encontraron trámites que coincidan.</flux:text>
                </template>

                <div class="mt-1 flex flex-col gap-2" x-show="!cargando && resultados.length > 0">
                    <template x-for="r in resultados" :key="r.id">
                        <a
                            :href="r.url"
                            wire:navigate
                            class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 transition hover:border-[#142f44]/40 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        >
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-[#142f44] dark:text-white" x-text="r.tracking"></div>
                                <div class="truncate text-xs text-zinc-500" x-text="(r.numero || '—') + ' · ' + (r.creador || '')"></div>
                            </div>
                            <span class="shrink-0 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950/40 dark:text-blue-300" x-text="r.estado"></span>
                        </a>
                    </template>
                </div>
            </div>
        </flux:card>
    </div>

    <script>
        (function () {
            function registrarBuscarTramites() {
                if (!window.Alpine) return;

                Alpine.data('buscarTramites', (endpoint) => ({
                    tipo: 'REQ',
                    codigo: '',
                    resultados: [],
                    cargando: false,
                    error: null,
                    peticionActual: 0,

                    init() {
                        this.buscar();
                    },

                    async buscar() {
                        const termino = this.codigo.trim();

                        if (termino.length < 2) {
                            this.resultados = [];
                            this.error = null;
                            this.cargando = false;
                            return;
                        }

                        const idPeticion = ++this.peticionActual;
                        this.cargando = true;
                        this.error = null;

                        try {
                            const url = endpoint + '?tipo=' + encodeURIComponent(this.tipo) + '&q=' + encodeURIComponent(termino);
                            const respuesta = await fetch(url, {
                                headers: { Accept: 'application/json' },
                            });

                            if (idPeticion !== this.peticionActual) return;

                            if (!respuesta.ok) {
                                this.resultados = [];
                                this.error = 'Ocurrió un error al buscar. Intenta nuevamente.';
                                return;
                            }

                            this.resultados = await respuesta.json();
                        } catch (e) {
                            if (idPeticion === this.peticionActual) {
                                this.resultados = [];
                                this.error = 'Ocurrió un error al buscar. Intenta nuevamente.';
                            }
                        } finally {
                            if (idPeticion === this.peticionActual) {
                                this.cargando = false;
                            }
                        }
                    },
                }));
            }

            document.addEventListener('alpine:init', registrarBuscarTramites);
            document.addEventListener('livewire:navigated', registrarBuscarTramites);
            registrarBuscarTramites();
        })();
    </script>
</x-layouts::app>
