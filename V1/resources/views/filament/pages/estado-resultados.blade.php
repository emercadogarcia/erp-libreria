<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filtro por fechas --}}
        <div class="fi-section rounded-xl bg-white p-4 shadow dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="fi-label text-sm">Desde</label>
                    <input type="date" wire:model.live="desde" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-800">
                </div>
                <div>
                    <label class="fi-label text-sm">Hasta</label>
                    <input type="date" wire:model.live="hasta" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-800">
                </div>
            </div>
        </div>

        {{-- Cuerpo del Estado de Resultados --}}
        <div class="mx-auto max-w-2xl rounded-xl bg-white p-6 shadow dark:bg-gray-900">
            <h2 class="mb-4 text-center text-lg font-bold">ESTADO DE RESULTADOS {{ strtoupper(config('accounting.empresa.nombre')) }}</h2>
            <p class="mb-6 text-center text-sm text-gray-500">
                Del {{ $desde ? \Carbon\Carbon::parse($desde)->format('d/m/Y') : 'inicio' }}
                al {{ $hasta ? \Carbon\Carbon::parse($hasta)->format('d/m/Y') : 'presente' }} · Expresado en Bs
            </p>

            <table class="w-full text-sm">
                <tbody>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <td class="py-2 font-semibold">Ventas netas</td>
                        <td class="py-2 text-right font-semibold">Bs {{ number_format($this->resultado['ventas'], 2) }}</td>
                    </tr>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <td class="py-2 pl-4">(-) Costo de ventas</td>
                        <td class="py-2 text-right">Bs {{ number_format($this->resultado['costo_ventas'], 2) }}</td>
                    </tr>
                    <tr class="border-b-2 border-gray-300 font-bold dark:border-gray-600">
                        <td class="py-2">Utilidad Bruta</td>
                        <td class="py-2 text-right">Bs {{ number_format($this->resultado['utilidad_bruta'], 2) }}</td>
                    </tr>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <td class="py-2 pl-4">(-) Gastos deducibles</td>
                        <td class="py-2 text-right">Bs {{ number_format($this->resultado['gastos_deducibles'], 2) }}</td>
                    </tr>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <td class="py-2 pl-4">(-) Gastos no deducibles (GND)</td>
                        <td class="py-2 text-right">Bs {{ number_format($this->resultado['gastos_no_deducibles'], 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-gray-300 text-base font-bold text-primary-600 dark:border-gray-600">
                        <td class="py-3">UTILIDAD NETA</td>
                        <td class="py-3 text-right">Bs {{ number_format($this->resultado['utilidad_neta'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
