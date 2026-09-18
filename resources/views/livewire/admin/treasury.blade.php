<div class="space-y-3">
    <div>
        <flux:heading size="xl" class="text-[#142f44]">Tesorería</flux:heading>
        <flux:text class="mt-1 text-zinc-500">Gestiona pagos pendientes de la obra activa.</flux:text>
    </div>

    @if (session('status'))<flux:callout variant="success" heading="{{ session('status') }}" />@endif
    @if ($errors->any())<flux:callout variant="danger" heading="{{ $errors->first() }}" />@endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="flex items-center justify-between p-3">
            <div>
                <flux:text class="text-zinc-500">Pagos pendientes</flux:text>
                <flux:heading size="xl" class="mt-1 text-[#142f44]">{{ $pendientesCount }}</flux:heading>
            </div>
            <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400">
                <flux:icon.clock class="size-5" />
            </div>
        </flux:card>

        <flux:card class="flex items-center justify-between p-3">
            <div>
                <flux:text class="text-zinc-500">Monto pendiente</flux:text>
                <flux:heading size="xl" class="mt-1 text-[#142f44]">S/ {{ number_format($pendientesMonto, 2) }}</flux:heading>
            </div>
            <div class="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400">
                <flux:icon.banknotes class="size-5" />
            </div>
        </flux:card>

        <flux:card class="flex items-center justify-between p-3">
            <div>
                <flux:text class="text-zinc-500">SP asignadas a Tesorería</flux:text>
                <flux:heading size="xl" class="mt-1 text-[#142f44]">{{ $spPendientesCount }}</flux:heading>
            </div>
            <div class="flex size-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/40 dark:text-purple-400">
                <flux:icon.document-currency-dollar class="size-5" />
            </div>
        </flux:card>

        <flux:card class="flex items-center justify-between p-3">
            <div>
                <flux:text class="text-zinc-500">Reembolsos por atender</flux:text>
                <flux:heading size="xl" class="mt-1 text-[#142f44]">{{ $reembolsos->count() }}</flux:heading>
            </div>
            <div class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                <flux:icon.arrow-uturn-left class="size-5" />
            </div>
        </flux:card>
    </div>

    <flux:card class="p-3">
        <flux:heading size="lg" class="text-[#142f44]">Solicitudes de pago</flux:heading>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                        <th class="pb-2 pe-3 font-medium">Trámite</th>
                        <th class="pb-2 pe-3 font-medium">Origen</th>
                        <th class="pb-2 pe-3 font-medium">Monto</th>
                        <th class="pb-2 pe-3 font-medium">Estado</th>
                        <th class="pb-2 font-medium">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($solicitudes as $solicitud)
                        <tr>
                            <td class="py-2.5 pe-3">
                                <a class="font-semibold text-[#142f44] underline dark:text-white" href="{{ route('tramites.show', $solicitud->tramite) }}" wire:navigate>
                                    {{ $solicitud->tramite->tracking }}
                                </a>
                            </td>
                            <td class="py-2.5 pe-3 text-zinc-600 dark:text-zinc-300">{{ $solicitud->origen }}</td>
                            <td class="py-2.5 pe-3 font-semibold text-[#142f44] dark:text-white">S/ {{ number_format($solicitud->monto, 2) }}</td>
                            <td class="py-2.5 pe-3">
                                <flux:badge size="sm" :color="match ($solicitud->estado) { 'Pendiente' => 'amber', 'Informativo' => 'blue', default => 'green' }">{{ $solicitud->estado }}</flux:badge>
                            </td>
                            <td class="py-2.5">
                                <flux:button size="sm" variant="ghost" icon="eye" wire:click="verDetalle({{ $solicitud->id }})" class="mb-2">
                                    Ver detalle
                                </flux:button>
                                @if ($solicitud->estado === 'Pendiente')
                                    <form wire:submit="pagarSolicitud({{ $solicitud->id }})" class="grid gap-2 sm:grid-cols-2">
                                        <flux:select label="Medio" wire:model.live="medio">
                                            <flux:select.option value="Transferencia">Transferencia</flux:select.option>
                                            <flux:select.option value="Depósito">Depósito</flux:select.option>
                                            <flux:select.option value="Efectivo">Efectivo</flux:select.option>
                                            <flux:select.option value="Yape">Yape</flux:select.option>
                                            <flux:select.option value="Plin">Plin</flux:select.option>
                                            <flux:select.option value="Otro">Otro</flux:select.option>
                                        </flux:select>
                                        @if ($medio === 'Otro')
                                            <flux:input label="Especifique el medio" wire:model="medioOtro" />
                                        @endif
                                        <flux:input type="date" label="Fecha" wire:model="fecha" />
                                        <flux:input type="number" step="0.01" label="Monto" wire:model="monto" />
                                        <flux:input label="Banco / entidad" wire:model="banco" />
                                        <flux:input label="N° operación" wire:model="operacion" />
                                        <div class="sm:col-span-2">
                                            <flux:label>Comprobante{{ $medio === 'Efectivo' ? ' (opcional en efectivo)' : '' }}</flux:label>
                                            <input type="file" multiple wire:model="comprobante" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full cursor-pointer rounded-lg border border-zinc-200 bg-white py-1.5 ps-1 text-sm text-zinc-600 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-200" />
                                            @error('comprobante') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
                                            @error('comprobante.*') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
                                        </div>
                                        @error('monto') <flux:text class="sm:col-span-2 text-sm text-red-500">{{ $message }}</flux:text> @enderror
                                        <flux:button type="submit" variant="primary" class="sm:col-span-2 !bg-[#142f44] hover:!bg-[#0d2032]">Registrar pago</flux:button>
                                    </form>
                                @else
                                    <div class="flex flex-col items-start gap-1">
                                        <flux:badge color="green">Atendida</flux:badge>
                                        @foreach ($solicitud->pagos as $pago)
                                            @if ($pago->nombre_archivo)
                                                <a href="{{ route('tesoreria.pagos.download', $pago) }}" class="text-xs text-blue-600 underline hover:text-blue-800">
                                                    {{ $pago->nombre_original ?? 'Comprobante' }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-center text-zinc-500">No hay solicitudes de pago.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <flux:card class="p-3">
        <flux:heading size="lg" class="text-[#142f44]">Solicitudes de Pago (SP)</flux:heading>
        <flux:text class="mt-1 text-zinc-500">Solicitudes de Pago asignadas a Tesorería para su atención.</flux:text>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                        <th class="pb-2 pe-3 font-medium">Trámite</th>
                        <th class="pb-2 pe-3 font-medium">Beneficiario</th>
                        <th class="pb-2 pe-3 font-medium">Fecha</th>
                        <th class="pb-2 pe-3 font-medium">Monto</th>
                        <th class="pb-2 pe-3 font-medium">Estado</th>
                        <th class="pb-2 font-medium">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($spTramites as $sp)
                        <tr>
                            <td class="py-2.5 pe-3">
                                <a class="font-semibold text-[#142f44] underline dark:text-white" href="{{ route('tramites.show', $sp) }}" wire:navigate>
                                    {{ $sp->tracking }}
                                </a>
                                <div class="text-xs text-zinc-500">{{ $sp->creador?->name }}</div>
                            </td>
                            <td class="py-2.5 pe-3 text-zinc-600 dark:text-zinc-300">{{ $sp->beneficiario }}</td>
                            <td class="py-2.5 pe-3 text-zinc-600 dark:text-zinc-300">{{ $sp->fecha?->format('d/m/Y') }}</td>
                            <td class="py-2.5 pe-3 font-semibold text-[#142f44] dark:text-white">
                                {{ $sp->moneda === 'USD' ? 'US$' : 'S/' }} {{ number_format($sp->abono, 2) }}
                            </td>
                            <td class="py-2.5 pe-3">
                                <flux:badge size="sm" :color="$sp->estado === 'Asignada a Tesorería' ? 'amber' : 'blue'">{{ $sp->estado }}</flux:badge>
                            </td>
                            <td class="py-2.5">
                                <flux:button size="sm" variant="primary" icon="banknotes" :href="route('tramites.show', $sp)" class="!bg-[#142f44] hover:!bg-[#0d2032]" wire:navigate>
                                    Registrar pago
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-center text-zinc-500">No hay Solicitudes de Pago asignadas a Tesorería.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <flux:card class="p-3">
        <flux:heading size="lg" class="text-[#142f44]">Reembolsos autorizados</flux:heading>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-100 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                        <th class="pb-2 pe-3 font-medium">Número</th>
                        <th class="pb-2 pe-3 font-medium">Solicitante</th>
                        <th class="pb-2 pe-3 font-medium">Monto</th>
                        <th class="pb-2 font-medium">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($reembolsos as $reembolso)
                        <tr>
                            <td class="py-2.5 pe-3 font-semibold text-[#142f44] dark:text-white">{{ $reembolso->numero }}</td>
                            <td class="py-2.5 pe-3 text-zinc-600 dark:text-zinc-300">{{ $reembolso->solicitante?->name }}</td>
                            <td class="py-2.5 pe-3">{{ $reembolso->moneda }} {{ number_format($reembolso->monto, 2) }}</td>
                            <td class="py-2.5">
                                <form wire:submit="atenderReembolso({{ $reembolso->id }})" class="flex flex-wrap items-end gap-2">
                                    <input type="file" wire:model="evidenciaReembolso" accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-1 block w-full cursor-pointer rounded-lg border border-zinc-200 bg-white py-1.5 ps-1 text-sm text-zinc-600 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-200" />
                                    <flux:button type="submit" variant="primary" class="!bg-[#142f44] hover:!bg-[#0d2032]">Atender</flux:button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-zinc-500">No hay reembolsos autorizados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    @if ($mostrarDetalle)
        <flux:modal wire:model="mostrarDetalle" name="detalle-solicitud" class="max-w-2xl">
            @if ($solicitudDetalle)
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg" class="text-[#142f44]">
                            Detalle de la solicitud · {{ $solicitudDetalle->tramite->tracking }}
                        </flux:heading>
                        <flux:text class="mt-1 text-zinc-500">
                            {{ $solicitudDetalle->origen }} · Solicitado por {{ $solicitudDetalle->solicitante?->name }}
                        </flux:text>
                    </div>

                    <div>
                        <flux:heading size="sm" class="text-[#142f44]">Ítems del trámite</flux:heading>
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-100 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                                        <th class="pb-2 pe-3 font-medium">Descripción</th>
                                        <th class="pb-2 pe-3 font-medium">Unidad</th>
                                        <th class="pb-2 pe-3 font-medium">Cantidad</th>
                                        <th class="pb-2 font-medium">Monto</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @forelse ($solicitudDetalle->tramite->items as $item)
                                        <tr>
                                            <td class="py-2 pe-3">{{ $item->descripcion }}</td>
                                            <td class="py-2 pe-3 text-zinc-600 dark:text-zinc-300">{{ $item->unidad }}</td>
                                            <td class="py-2 pe-3 text-zinc-600 dark:text-zinc-300">{{ $item->cantidad }}</td>
                                            <td class="py-2">{{ $item->monto ? 'S/ '.number_format($item->monto, 2) : '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="py-3 text-center text-zinc-500">Este trámite no tiene ítems registrados.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <flux:heading size="sm" class="text-[#142f44]">Historial de pagos parciales</flux:heading>
                        <div class="mt-2 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-100 text-xs text-zinc-500 uppercase dark:border-zinc-800">
                                        <th class="pb-2 pe-3 font-medium">Fecha</th>
                                        <th class="pb-2 pe-3 font-medium">Medio</th>
                                        <th class="pb-2 pe-3 font-medium">Banco / operación</th>
                                        <th class="pb-2 pe-3 font-medium">Monto</th>
                                        <th class="pb-2 font-medium">Comprobante</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @forelse ($solicitudDetalle->pagos as $pago)
                                        <tr>
                                            <td class="py-2 pe-3">{{ $pago->created_at?->format('d/m/Y H:i') }}</td>
                                            <td class="py-2 pe-3 text-zinc-600 dark:text-zinc-300">{{ $pago->medio_pago }}</td>
                                            <td class="py-2 pe-3 text-zinc-600 dark:text-zinc-300">{{ $pago->banco }} {{ $pago->nro_operacion ? '· '.$pago->nro_operacion : '' }}</td>
                                            <td class="py-2 pe-3 font-semibold text-[#142f44] dark:text-white">S/ {{ number_format($pago->monto, 2) }}</td>
                                            <td class="py-2">
                                                @if ($pago->nombre_archivo)
                                                    <a href="{{ route('tesoreria.pagos.download', $pago) }}" class="text-xs text-blue-600 underline hover:text-blue-800">
                                                        {{ $pago->nombre_original ?? 'Ver' }}
                                                    </a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="py-3 text-center text-zinc-500">Aún no se registran pagos para esta solicitud.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($historialDetalle->isNotEmpty())
                        <div>
                            <flux:heading size="sm" class="text-[#142f44]">Historial del trámite</flux:heading>
                            <ul class="mt-2 space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                                @foreach ($historialDetalle as $evento)
                                    <li>
                                        <span class="text-zinc-400">{{ $evento->created_at?->format('d/m/Y H:i') }}</span>
                                        — {{ $evento->accion }}
                                        @if ($evento->usuario)
                                            <span class="text-zinc-400">({{ $evento->usuario->name }})</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <flux:button variant="ghost" wire:click="cerrarDetalle">Cerrar</flux:button>
                    </div>
                </div>
            @endif
        </flux:modal>
    @endif
</div>
