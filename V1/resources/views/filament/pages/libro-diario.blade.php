<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filtro por fechas --}}
        <div class="fi-section rounded-xl bg-white p-4 shadow dark:bg-gray-900">
            <form wire:submit="save">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="fi-label text-sm">Desde</label>
                        <input type="date" wire:model.live="desde" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-800">
                    </div>
                    <div>
                        <label class="fi-label text-sm">Hasta</label>
                        <input type="date" wire:model.live="hasta" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-800">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-white">Filtrar</button>
                        <button type="button" wire:click="$refresh" class="ml-2 rounded-lg border px-4 py-2">Limpiar</button>
                    </div>
                    <div class="flex items-center sm:col-span-3">
                        @if ($this->totales['cuadrado'])
                            <span class="rounded-full bg-success-100 px-3 py-1 text-sm text-success-700 dark:bg-success-900/30 dark:text-success-300">
                                ✓ Partida doble: Debe (Bs {{ number_format($this->totales['debe'], 2) }}) = Haber (Bs {{ number_format($this->totales['haber'], 2) }})
                            </span>
                        @else
                            <span class="rounded-full bg-danger-100 px-3 py-1 text-sm text-danger-700 dark:bg-danger-900/30 dark:text-danger-300">
                                ⚠ DESCUADRE detectado: Debe Bs {{ number_format($this->totales['debe'], 2) }} vs Haber Bs {{ number_format($this->totales['haber'], 2) }}
                            </span>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        {{-- Asientos del Libro Diario --}}
        <div class="space-y-4">
            @forelse ($this->asientos as $asiento)
                <div class="rounded-xl bg-white p-4 shadow dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-2 dark:border-gray-700">
                        <div>
                            <span class="font-mono font-semibold">{{ $asiento->numero_asiento }}</span>
                            <span class="ml-2 text-sm text-gray-500">{{ $asiento->fecha_asiento->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-300">{{ $asiento->glosa }}</div>
                    </div>
                    <table class="mt-2 w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500">
                                <th class="py-1 text-left">Cuenta</th>
                                <th class="py-1 text-right">Debe</th>
                                <th class="py-1 text-right">Haber</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($asiento->detalles as $detalle)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-1">
                                        <span class="font-mono text-xs">{{ $detalle->cuenta_codigo }}</span>
                                        — {{ optional($detalle->planCuenta)->nombre ?? 'Cuenta no registrada' }}
                                    </td>
                                    <td class="py-1 text-right">{{ (float) $detalle->debe > 0 ? 'Bs '.number_format($detalle->debe, 2) : '' }}</td>
                                    <td class="py-1 text-right">{{ (float) $detalle->haber > 0 ? 'Bs '.number_format($detalle->haber, 2) : '' }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-t-2 border-gray-300 font-semibold dark:border-gray-600">
                                <td class="py-1">Totales</td>
                                <td class="py-1 text-right">Bs {{ number_format($asiento->total_debe, 2) }}</td>
                                <td class="py-1 text-right">Bs {{ number_format($asiento->total_haber, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="rounded-xl bg-white p-8 text-center text-gray-500 shadow dark:bg-gray-900">
                    No hay asientos en el rango seleccionado. Aprobá ventas o registrá compras para generar movimientos.
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
