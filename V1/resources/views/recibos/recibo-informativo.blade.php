{{-- ================================================================= --}}
{{-- RECIBO INFORMATIVO DE VENTA (PRD Módulo 6) --}}
{{-- Estructura visual exigida por Impuestos Nacionales de Bolivia. --}}
{{-- Documento SIMULADO: los códigos CUFD/CUIS/Código de Control son --}}
{{-- teóricos; el MVP no está conectado a los Web Services del SIN. --}}
{{-- ================================================================= --}}
@php
    $empresa = config('accounting.empresa');
    $factura = $venta->factura()->latest('id')->first();
    $iva = config('accounting.iva');
    $baseCreditoFiscal = $factura?->total_base_credito_fiscal ?? round($venta->total / (1 + $iva), 2);
    $debitoFiscal = $factura?->debito_fiscal ?? round($venta->total - $venta->total / (1 + $iva), 2);
    $leyenda = $factura?->leyenda ?? config('accounting.leyendas.LEY-453');
@endphp

<div class="mx-auto max-w-2xl bg-white p-8 shadow-sm print:shadow-none" style="font-family: ui-monospace, 'Courier New', monospace;">
    {{-- Encabezado: datos de la empresa --}}
    <div class="text-center">
        <h1 class="text-lg font-bold uppercase">{{ $empresa['nombre'] }}</h1>
        <div class="mt-1 text-xs">
            <div>{{ $empresa['direccion'] }} · {{ $empresa['ciudad'] }}</div>
            <div>Tel: {{ $empresa['telefono'] }} · {{ $empresa['email'] }}</div>
        </div>
        <div class="mt-2 inline-block rounded border-2 border-stone-800 px-6 py-1">
            <div class="text-sm font-bold">
                {{ $factura ? 'FACTURA' : 'RECIBO INFORMATIVO DE VENTA' }}
            </div>
            <div class="text-xs">N° {{ $factura?->numero_factura ?? $venta->numero_recibo }}</div>
        </div>
        <div class="mt-1 text-[10px] text-stone-500">
            {{ $empresa['sucursal'] }} · {{ $empresa['punto_venta'] }}
        </div>
    </div>

    {{-- Datos fiscales simulados --}}
    <div class="mt-4 space-y-0.5 border-y border-dashed border-stone-400 py-2 text-[11px]">
        <div class="flex justify-between"><span>NIT:</span><span>{{ $empresa['nit'] }}</span></div>
        <div class="flex justify-between"><span>CUIS:</span><span>{{ $factura?->cuis ?? $empresa['cuis'] }}</span></div>
        <div class="flex justify-between"><span>CUFD:</span><span>{{ $factura?->cufd ?? $empresa['cufd'] }}</span></div>
        @if ($factura)
            <div class="flex justify-between"><span>CUF:</span><span class="break-all">{{ $factura->cuf }}</span></div>
            <div class="flex justify-between"><span>Cód. Control:</span><span>{{ $factura->codigo_control }}</span></div>
        @endif
        <div class="flex justify-between"><span>Fecha:</span><span>{{ $venta->created_at->format('d/m/Y H:i') }}</span></div>
    </div>

    {{-- Datos del cliente --}}
    <div class="mt-3 space-y-0.5 text-xs">
        <div><span class="font-semibold">Señor(es):</span> {{ $venta->cliente_nombre ?? 'Cliente Ocasional' }}</div>
        <div><span class="font-semibold">NIT/CI:</span> {{ $venta->cliente_nit_ci ?? '0' }}</div>
        @if ($venta->cliente_email)
            <div><span class="font-semibold">Email:</span> {{ $venta->cliente_email }}</div>
        @endif
    </div>

    {{-- Detalle del producto --}}
    <table class="mt-4 w-full text-xs">
        <thead>
            <tr class="border-y border-stone-800 text-left">
                <th class="py-1">Cant.</th>
                <th class="py-1">Detalle</th>
                <th class="py-1 text-right">P. Unit.</th>
                <th class="py-1 text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venta->detalles as $detalle)
                <tr class="border-b border-stone-200">
                    <td class="py-1 align-top">{{ $detalle->cantidad }}</td>
                    <td class="py-1 align-top">
                        {{ $detalle->articulo->titulo_nombre }}
                        @if ($detalle->articulo->autor)
                            <span class="text-stone-500">— {{ $detalle->articulo->autor }}</span>
                        @endif
                    </td>
                    <td class="py-1 text-right align-top">{{ number_format($detalle->precio_unitario, 2) }}</td>
                    <td class="py-1 text-right align-top">{{ number_format($detalle->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totales con desglose fiscal --}}
    <div class="mt-3 flex justify-end">
        <div class="w-64 space-y-0.5 text-xs">
            <div class="flex justify-between"><span>SUBTOTAL Bs:</span><span>{{ number_format($venta->subtotal, 2) }}</span></div>
            @if ((float) $venta->descuento > 0)
                <div class="flex justify-between"><span>DESCUENTO Bs:</span><span>-{{ number_format($venta->descuento, 2) }}</span></div>
            @endif
            <div class="flex justify-between"><span>TOTAL Bs:</span><span class="font-bold">{{ number_format($venta->total, 2) }}</span></div>
            <div class="flex justify-between border-t border-dashed border-stone-400 pt-1"><span>Total Base Crédito Fiscal:</span><span>{{ number_format($baseCreditoFiscal, 2) }}</span></div>
            <div class="flex justify-between"><span>Débito Fiscal (IVA 13%):</span><span>{{ number_format($debitoFiscal, 2) }}</span></div>
        </div>
    </div>

    {{-- Código de control visual (simulado) --}}
    <div class="mt-4 text-center">
        <div class="text-[10px] text-stone-500">CÓDIGO DE CONTROL (SIMULADO)</div>
        <div class="mt-1 inline-block border border-stone-400 px-3 py-1 text-[10px] tracking-wider">
            {{ $factura?->codigo_control ?? 'XXXX-XXXX-XXXX-XXXX-XXXX' }}
        </div>
        @if ($factura)
            <div class="mt-1 text-[9px] break-all text-stone-400">Firma digital (hash): {{ substr($factura->firma_digital_hash, 0, 32) }}…</div>
        @endif
    </div>

    {{-- Leyendas obligatorias --}}
    <div class="mt-4 border-t border-stone-300 pt-2 text-center text-[10px] leading-snug">
        <div class="italic">{{ $leyenda }}</div>
        <div class="mt-1 font-semibold">{{ config('accounting.leyendas.FACTURA-SIMULADA') }}</div>
    </div>
</div>
