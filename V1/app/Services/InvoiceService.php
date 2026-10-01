<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

/**
 * Simulador de Facturación Electrónica en Línea (PRD Módulo 6).
 *
 * NO se conecta a los Web Services del SIN ni usa firmas reales de ADSIB.
 * Al facturar, el backend genera una cadena simulando el hash de la firma
 * digital y los códigos únicos CUFD/CUIS/CUF exigidos por Impuestos Nacionales.
 */
class InvoiceService
{
    /**
     * Emite un Recibo/Factura electrónica simulada para una venta aprobada.
     * Estructura y valida internamente los campos obligatorios del SIN.
     *
     * @param  Venta  $venta  Venta aprobada (estado Aprobada).
     * @return Factura
     *
     * @throws Exception Si la venta no está aprobada o ya tiene factura.
     */
    public static function emitirFactura(Venta $venta): Factura
    {
        // La facturación solo procede sobre ventas consolidadas
        if ($venta->estado !== 'Aprobada') {
            throw new Exception('Solo se facturan ventas aprobadas (estado actual: '.$venta->estado.').');
        }

        // Una venta solo puede tener una factura activa
        if ($venta->factura()->exists()) {
            throw new Exception('La venta ya tiene una factura emitida.');
        }

        return DB::transaction(function () use ($venta) {
            $empresa = config('accounting.empresa');
            $iva = config('accounting.iva');

            // 1. Generación simulada de los códigos exigidos por el SIN
            $cuis = $empresa['cuis'];                              // Código Único de Inscripción de Sucursal
            $cufd = $empresa['cufd'];                              // Código Único de Facturación Diaria
            $cuf = self::generarCuf();                             // Código Único de Factura
            $codigoControl = self::generarCodigoControl();         // Código de Control (base45 teórico)
            $firmaDigital = hash('sha256', $venta->numero_recibo.'|'.$venta->total.'|'.$cuf.'|'.config('app.key')); // Hash simulado de firma

            // 2. Desglose fiscal: Total Base Crédito Fiscal + Débito Fiscal IVA 13%
            $baseCreditoFiscal = round((float) $venta->total / (1 + $iva), 2);
            $debitoFiscal = round((float) $venta->total - $baseCreditoFiscal, 2);

            $factura = Factura::create([
                'venta_id' => $venta->id,
                'numero_factura' => self::siguienteNumeroFactura(),
                'cuis' => $cuis,
                'cufd' => $cufd,
                'cuf' => $cuf,
                'codigo_control' => $codigoControl,
                'firma_digital_hash' => $firmaDigital,
                'fecha_emision' => now(),
                'total_base_credito_fiscal' => $baseCreditoFiscal,
                'debito_fiscal' => $debitoFiscal,
                'leyenda' => config('accounting.leyendas.LEY-453'),
            ]);

            return $factura;
        });
    }

    /**
     * Genera el CUF simulado (16 dígitos hexadecimales con prefijo temporal).
     */
    protected static function generarCuf(): string
    {
        return 'CUF-'.strtoupper(Str::random(16));
    }

    /**
     * Genera un Código de Control simulado en bloques alfanuméricos.
     */
    protected static function generarCodigoControl(): string
    {
        $bloques = [];

        for ($i = 0; $i < 5; $i++) {
            $bloques[] = strtoupper(Str::random(4));
        }

        return implode('-', $bloques);
    }

    /**
     * Correlativo de factura simulado (FAC-000001, secuencial por tabla).
     */
    protected static function siguienteNumeroFactura(): string
    {
        return 'FAC-'.str_pad((string) ((int) Factura::max('id') + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Datos completos para la vista de impresión del Recibo Informativo
     * (formato carta/tique con estructura visual de Impuestos Nacionales).
     *
     * @return array<string, mixed>
     */
    public static function datosRecibo(Venta $venta): array
    {
        $empresa = config('accounting.empresa');
        $factura = $venta->factura()->latest('id')->first();

        return [
            'empresa' => $empresa,
            'venta' => $venta->load(['detalles.articulo', 'cliente']),
            'factura' => $factura,
            'leyenda' => $factura?->leyenda ?? config('accounting.leyendas.FACTURA-SIMULADA'),
        ];
    }
}
