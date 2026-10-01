<div class="fi-ta-ctn">
    <table class="fi-ta-table w-full table-auto">
        <thead>
            <tr class="text-xs text-gray-500 dark:text-gray-400">
                <th class="px-3 py-2 text-left">Fecha</th>
                <th class="px-3 py-2 text-left">Movimiento</th>
                <th class="px-3 py-2 text-left">Documento</th>
                <th class="px-3 py-2 text-right">Entrada</th>
                <th class="px-3 py-2 text-right">Salida</th>
                <th class="px-3 py-2 text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movimientos as $mov)
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td class="px-3 py-2 whitespace-nowrap">{{ $mov['fecha'] }}</td>
                    <td class="px-3 py-2">{{ $mov['tipo'] }}</td>
                    <td class="px-3 py-2 font-mono text-xs">{{ $mov['documento'] }}</td>
                    <td class="px-3 py-2 text-right text-success-600">{{ $mov['entrada'] > 0 ? $mov['entrada'] : '' }}</td>
                    <td class="px-3 py-2 text-right text-danger-600">{{ $mov['salida'] > 0 ? $mov['salida'] : '' }}</td>
                    <td class="px-3 py-2 text-right font-semibold">{{ $mov['saldo'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-3 py-4 text-center text-gray-500">Sin movimientos registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
