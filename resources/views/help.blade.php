<x-layouts::app :title="__('Ayuda del sistema')">
    <div class="mx-auto max-w-6xl space-y-6 p-6">
        <div class="flex flex-col gap-2 border-b border-zinc-200 pb-6 dark:border-zinc-700 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <flux:heading size="xl">Ayuda del sistema</flux:heading>
                <flux:text class="mt-1 text-zinc-500">Guía operativa para LANR Inversiones · versión 2.0</flux:text>
            </div>
            <flux:badge color="green">Rol: {{ auth()->user()->getRoleNames()->join(', ') ?: 'Usuario' }}</flux:badge>
        </div>

        <div class="grid gap-6 lg:grid-cols-[220px_1fr]">
            <aside class="h-fit lg:sticky lg:top-6">
                <nav class="flex flex-col gap-1 rounded-lg border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-700 dark:bg-zinc-900">
                    <a href="#inicio" class="rounded-md px-3 py-2 text-sm font-medium hover:bg-white dark:hover:bg-zinc-800">Inicio</a>
                    <a href="#requerimientos" class="rounded-md px-3 py-2 text-sm font-medium hover:bg-white dark:hover:bg-zinc-800">Requerimientos</a>
                    <a href="#solicitudes" class="rounded-md px-3 py-2 text-sm font-medium hover:bg-white dark:hover:bg-zinc-800">Solicitudes de pago</a>
                    @if (auth()->user()->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Sistemas']))
                        <a href="#aprobaciones" class="rounded-md px-3 py-2 text-sm font-medium hover:bg-white dark:hover:bg-zinc-800">Aprobaciones</a>
                    @endif
                    @if (auth()->user()->hasAnyRole(['Logística', 'Sistemas']))
                        <a href="#logistica" class="rounded-md px-3 py-2 text-sm font-medium hover:bg-white dark:hover:bg-zinc-800">Logística</a>
                    @endif
                    @if (auth()->user()->hasAnyRole(['Tesorería', 'Sistemas']))
                        <a href="#tesoreria" class="rounded-md px-3 py-2 text-sm font-medium hover:bg-white dark:hover:bg-zinc-800">Tesorería</a>
                    @endif
                    @if (auth()->user()->hasAnyRole(['Gerencia General', 'Sistemas']))
                        <a href="#gerencia" class="rounded-md px-3 py-2 text-sm font-medium hover:bg-white dark:hover:bg-zinc-800">Gerencia General</a>
                    @endif
                    @if (auth()->user()->hasRole('Sistemas'))
                        <a href="#sistemas" class="rounded-md px-3 py-2 text-sm font-medium hover:bg-white dark:hover:bg-zinc-800">Sistemas</a>
                    @endif
                    <a href="#estados" class="rounded-md px-3 py-2 text-sm font-medium hover:bg-white dark:hover:bg-zinc-800">Estados</a>
                </nav>
            </aside>

            <main class="space-y-6">
                <section id="inicio" class="scroll-mt-6 rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="lg">Cómo funciona el sistema</flux:heading>
                    <flux:text class="mt-2">LANR centraliza requerimientos de materiales y solicitudes de pago de la obra La Vega. Cada operación registra usuario, fecha, estado e historial.</flux:text>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ([['1', 'Registrar', 'El creador ingresa el trámite y sus conceptos.'], ['2', 'Revisar', 'Los responsables dan su visto bueno.'], ['3', 'Gestionar', 'Logística, Tesorería o Gerencia ejecutan el proceso.'], ['4', 'Cerrar', 'La recepción o conformidad final cierra el trámite.']] as [$number, $title, $description])
                            <div class="border-l-2 border-emerald-500 pl-3">
                                <div class="text-xs font-bold text-emerald-600">PASO {{ $number }}</div>
                                <div class="mt-1 font-semibold">{{ $title }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ $description }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section id="requerimientos" class="scroll-mt-6 rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="lg">Requerimientos de materiales</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-zinc-600 marker:text-emerald-500 dark:text-zinc-300">
                        <li>En <strong>Nuevo trámite</strong>, selecciona requerimiento, ingresa número y fecha, y agrega al menos un ítem.</li>
                        <li>Cada ítem requiere sección, descripción, unidad y cantidad. El sistema calcula automáticamente <strong>Comprar = Cantidad - Stock</strong>, sin permitir valores negativos.</li>
                        <li>Al registrar, el requerimiento queda en <strong>Pendiente de aprobación</strong> y se asigna a Control y Planeamiento y Gerencia de Obra.</li>
                        <li>Cuando ambos vistos buenos están registrados, pasa a Logística.</li>
                        <li>Puedes adjuntar documentos de respaldo al registrar el trámite y descargarlos desde su expediente.</li>
                    </ul>
                </section>

                <section id="solicitudes" class="scroll-mt-6 rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="lg">Solicitudes de pago</flux:heading>
                    <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-zinc-600 marker:text-emerald-500 dark:text-zinc-300">
                        <li>Selecciona <strong>Solicitud de Pago</strong> y elige Planilla o Persona/Empresa.</li>
                        <li>Registra beneficiario, modalidad, conceptos, cantidades y costos. El total se calcula automáticamente.</li>
                        <li>La solicitud requiere aprobación de Logística y Administración. Después Gerencia General asigna el pago a Tesorería o lo asume directamente.</li>
                        <li>El comprobante de pago es obligatorio y el monto pagado debe coincidir con el total solicitado.</li>
                    </ul>
                </section>

                @if (auth()->user()->hasAnyRole(['Gerencia de Obra', 'Control y Planeamiento', 'Sistemas']))
                    <section id="aprobaciones" class="scroll-mt-6 rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                        <flux:heading size="lg">Aprobaciones y recepción en obra</flux:heading>
                        <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-zinc-600 marker:text-emerald-500 dark:text-zinc-300">
                            <li>Las aprobaciones pendientes aparecen en el inicio. Revisa el detalle antes de dar el visto bueno.</li>
                            <li>Gerencia de Obra, Control y Planeamiento y Sistemas pueden confirmar la recepción de un requerimiento enviado a obra.</li>
                            <li>La recepción cambia el estado a <strong>Cerrado</strong> y queda registrada en el historial.</li>
                        </ul>
                    </section>
                @endif

                @if (auth()->user()->hasAnyRole(['Logística', 'Sistemas']))
                    <section id="logistica" class="scroll-mt-6 rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                        <flux:heading size="lg">Gestión de Logística</flux:heading>
                        <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-zinc-600 marker:text-emerald-500 dark:text-zinc-300">
                            <li>Recibe los requerimientos aprobados y registra fecha, comprobante y monto de compra.</li>
                            <li>Si falta documentación, marca la regularización pendiente. El sistema mantiene el pendiente en el expediente.</li>
                            <li>Una compra pagada puede enviarse a obra con número y fecha de guía. El pago debe estar registrado antes del despacho.</li>
                            <li>Las operaciones se reflejan en la bandeja de pendientes y en el historial del trámite.</li>
                            <li>Los comprobantes y guías pueden adjuntarse durante cada etapa y quedan disponibles en el expediente.</li>
                        </ul>
                    </section>
                @endif

                @if (auth()->user()->hasAnyRole(['Tesorería', 'Sistemas']))
                    <section id="tesoreria" class="scroll-mt-6 rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                        <flux:heading size="lg">Tesorería</flux:heading>
                        <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-zinc-600 marker:text-emerald-500 dark:text-zinc-300">
                            <li>Las solicitudes asignadas aparecen en la bandeja de Tesorería.</li>
                            <li>Verifica el monto, registra medio, banco, fecha y número de operación, y adjunta el comprobante.</li>
                            <li>Una vez registrado el pago, Gerencia General recibe la solicitud para su conformidad final.</li>
                            <li>Los pagos de compras requieren comprobante y quedan vinculados al trámite.</li>
                        </ul>
                    </section>
                @endif

                @if (auth()->user()->hasAnyRole(['Gerencia General', 'Sistemas']))
                    <section id="gerencia" class="scroll-mt-6 rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                        <flux:heading size="lg">Gerencia General</flux:heading>
                        <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-zinc-600 marker:text-emerald-500 dark:text-zinc-300">
                            <li>Revisa solicitudes con aprobaciones completas y decide quién realizará el pago.</li>
                            <li>Cuando el pago fue registrado, revisa el comprobante y confirma la conformidad final.</li>
                            <li>La conformidad final cambia el estado a <strong>Cerrado</strong>.</li>
                        </ul>
                    </section>
                @endif

                @if (auth()->user()->hasRole('Sistemas'))
                    <section id="sistemas" class="scroll-mt-6 rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                        <flux:heading size="lg">Sistemas</flux:heading>
                        <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-zinc-600 marker:text-emerald-500 dark:text-zinc-300">
                            <li>Sistemas puede consultar y asistir operaciones, aprobar cuando corresponda y administrar usuarios.</li>
                            <li>La administración de usuarios permite activar, desactivar y restablecer accesos según las políticas internas.</li>
                            <li>Las acciones de asistencia quedan identificadas en el historial con el usuario que las ejecutó.</li>
                        </ul>
                    </section>
                @endif

                <section id="estados" class="scroll-mt-6 rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="lg">Estados del proceso</flux:heading>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach ([['Pendiente de aprobación', 'Faltan vistos buenos asignados.'], ['Aprobado', 'El requerimiento puede pasar a Logística.'], ['Recibido por Logística', 'Logística confirmó la recepción.'], ['En gestión de compra', 'La compra fue registrada.'], ['Enviado a obra', 'La compra está pagada y fue despachada.'], ['Pendiente asignación de pago', 'Gerencia debe asignar el responsable.'], ['Pagada pendiente conformidad GG', 'El pago existe y espera revisión final.'], ['Cerrado', 'El flujo terminó correctamente.']] as [$state, $description])
                            <div class="rounded-md bg-zinc-50 px-3 py-2 dark:bg-zinc-900">
                                <div class="font-medium">{{ $state }}</div>
                                <div class="text-sm text-zinc-500">{{ $description }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </main>
        </div>
    </div>
</x-layouts::app>
