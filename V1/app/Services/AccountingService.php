<?php

namespace App\Services;

use App\Models\AsientoDiario;
use App\Models\AjusteInventario;
use App\Models\CompraCabecera;
use App\Models\PagoCliente;
use App\Models\PlanCuenta;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Exception;

/**
 * Clase de Servicio para la automatización de procesos contables del ERP (PRD Módulo 5).
 * Balancea y genera la partida doble requerida por la normativa boliviana.
 *
 * Variables financieras procesadas:
 *  - $venta->total: monto total en Bs. de la venta aprobada.
 *  - $venta->es_credito: determina si se debita Caja/Banco o Cuentas por Cobrar.
 *  - IVA (13%): desglose simulado Débito Fiscal (crédito al asiento de venta).
 *  - IT (3%): impuesto a las transacciones, asiento compuesto simultáneo.
 *  - CMP: costo medio ponderado del artículo al momento de la venta (Costo de Ventas).
 */
class AccountingService
{
    /**
     * Resuelve el código del plan de cuentas desde la configuración.
     *
     * @param  string  $clave  Clave semántica (ej. 'caja_general', 'debito_fiscal').
     * @return string  Código de cuenta (ej. '1.1.1.01').
     *
     * @throws InvalidArgumentException Si la cuenta no está definida.
     */
    public static function cuenta(string $clave): string
    {
        $codigo = config("accounting.cuentas.{$clave}");

        if (! $codigo) {
            throw new InvalidArgumentException("Cuenta contable no configurada: [{$clave}].");
        }

        return $codigo;
    }

    /**
     * Genera el siguiente número correlativo de asiento con bloqueo de fila
     * (nadie más puede tomar el mismo número dentro de la transacción).
     */
    public static function siguienteNumeroAsiento(): string
    {
        $max = AsientoDiario::query()->max('id');
        $correlativo = (int) $max + 1;

        return 'AJ-'.str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Punto único de registro de asientos: valida la partida doble antes de
     * persistir (Debe - Haber = 0). Si no cuadra, aborta la transacción.
     *
     * @param  string  $glosa  Descripción del movimiento.
     * @param  array<int, array{cuenta:string, debe?:float, haber?:float}>  $lineas
     * @param  string  $origen  venta|compra|ajuste|credito|manual
     * @param  ?int  $referenciaId  ID del documento origen.
     * @param  ?string  $fecha  Fecha del asiento (Y-m-d H:i:s).
     *
     * @throws Exception Si el asiento no cumple el principio de partida doble.
     */
    public static function registrarAsiento(
        string $glosa,
        array $lineas,
        string $origen = 'manual',
        ?int $referenciaId = null,
        ?string $fecha = null,
    ): AsientoDiario {
        // Normalización: se eliminan líneas de monto cero (ruido contable)
        $lineas = array_values(array_filter($lineas, fn (array $l) => (float) ($l['debe'] ?? 0) != 0.0 || (float) ($l['haber'] ?? 0) != 0.0));

        $totalDebe = round(array_sum(array_map(fn (array $l) => (float) ($l['debe'] ?? 0), $lineas)), 2);
        $totalHaber = round(array_sum(array_map(fn (array $l) => (float) ($l['haber'] ?? 0), $lineas)), 2);

        // Validación técnica de seguridad (Partida Doble Estricta): Debe - Haber = 0
        if (abs($totalDebe - $totalHaber) > 0.0001) {
            throw new Exception(
                "Error Crítico: El asiento contable no cuadra (Debe {$totalDebe} vs Haber {$totalHaber}). Transacción abortada."
            );
        }

        if ($totalDebe == 0.0) {
            throw new Exception('Error Crítico: No se puede registrar un asiento de monto cero.');
        }

        $asiento = AsientoDiario::create([
            'numero_asiento' => self::siguienteNumeroAsiento(),
            'glosa' => $glosa,
            'fecha_asiento' => $fecha ?? now(),
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'origen' => $origen,
            'referencia_id' => $referenciaId,
        ]);

        foreach ($lineas as $linea) {
            $asiento->detalles()->create([
                'cuenta_codigo' => $linea['cuenta'],
                'debe' => round((float) ($linea['debe'] ?? 0), 2),
                'haber' => round((float) ($linea['haber'] ?? 0), 2),
            ]);
        }

        return $asiento->fresh(['detalles']);
    }

    /**
     * Registra el asiento automático a partir de una venta consolidada (PRD Módulo 5):
     * Debita Caja/Banco (o Cuentas por Cobrar si es crédito) y Costo de Ventas;
     * acredita Ventas (87% neto), Débito Fiscal IVA (13%) e Inventarios (CMP).
     *
     * @param  Venta  $venta  Modelo de la venta aprobada en el sistema.
     * @return AsientoDiario
     *
     * @throws Exception Si el asiento no cumple con el principio de partida doble.
     */
    public static function generarAsientoVenta(Venta $venta): AsientoDiario
    {
        // Se ejecuta bajo una transacción segura de base de datos
        return DB::transaction(function () use ($venta) {
            $venta->load(['detalles.articulo']);

            $total = round((float) $venta->total, 2);
            $iva = config('accounting.iva');       // 0.13
            $itRate = config('accounting.it');     // 0.03

            // 1. Desglose fiscal simulado (base imponible neta + IVA 13%)
            $baseImponible = round($total / (1 + $iva), 2);   // 87% aprox. del total
            $debitoFiscal = round($total - $baseImponible, 2); // IVA del total

            // 2. IT 3% sobre la base imponible (asiento compuesto simultáneo)
            $itMonto = round($baseImponible * $itRate, 2);

            // 3. Contrapartida del IT: se grava la cuenta de ingresos (neto tras IT)
            $ingresoNeto = round($baseImponible - $itMonto, 2);

            // 4. Costo de Ventas = suma del CMP histórico congelado por línea
            $costoVentas = round(
                $venta->detalles->sum(fn (VentaDetalle $d) => $d->cantidad * (float) $d->costo_unitario_historico),
                2
            );

            $glosa = "Asiento automático - Venta según Recibo Nro. {$venta->numero_recibo}";

            // 5. Asiento principal de la venta
            //    DEBE: Caja/Banco (contado) o Cuentas por Cobrar (crédito) por el 100%
            $lineasVenta = [
                [
                    'cuenta' => $venta->es_credito ? self::cuenta('cuentas_por_cobrar') : ($venta->forma_pago === 'QR' ? self::cuenta('banco') : self::cuenta('caja_general')),
                    'debe' => $total,
                ],
                ['cuenta' => self::cuenta('ventas'), 'haber' => $ingresoNeto],
                ['cuenta' => self::cuenta('debito_fiscal'), 'haber' => $debitoFiscal],
                ['cuenta' => self::cuenta('transacciones_it'), 'haber' => $itMonto],
            ];

            // 6. Asiento compuesto de costo: Costo de Ventas (DEBE) vs Inventarios (HABER)
            $asientoVenta = self::registrarAsiento($glosa, $lineasVenta, 'venta', $venta->id);

            if ($costoVentas > 0) {
                self::registrarAsiento(
                    "Costo de ventas - Recibo Nro. {$venta->numero_recibo}",
                    [
                        ['cuenta' => self::cuenta('costo_ventas'), 'debe' => $costoVentas],
                        ['cuenta' => self::cuenta('inventarios'), 'haber' => $costoVentas],
                    ],
                    'venta',
                    $venta->id
                );
            }

            return $asientoVenta;
        });
    }

    /**
     * Asiento de compra (PRD Módulo 5): separa Crédito Fiscal (13%) si la compra
     * es deducible, o asigna el 100% a GND interno si no hay factura fiscal.
     *
     * Lógica:
     *  - Nacional deducible: DEBE Inventarios (87%) + Crédito Fiscal (13%) | HABER Proveedores (100%).
     *  - Internacional deducible: igual, con cuentas por pagar exteriores.
     *  - GND: DEBE Inventarios (100%) | HABER Proveedores (100%), sin crédito fiscal.
     *
     * @return AsientoDiario|null Null si la compra aún no tiene valor FOB.
     *
     * @throws Exception Si el asiento no cumple con el principio de partida doble.
     */
    public static function generarAsientoCompra(CompraCabecera $compra): ?AsientoDiario
    {
        return DB::transaction(function () use ($compra) {
            $compra->load(['detalles']);

            $totalFob = round((float) $compra->total_fob, 2);

            if ($totalFob <= 0) {
                return null; // Una compra sin valor aún no genera asiento
            }

            $iva = config('accounting.iva');

            if ($compra->es_gnd) {
                // Gasto No Deducible: 100% al inventario, sin desglose fiscal (QA #1)
                $baseInventario = $totalFob;
                $creditoFiscal = 0.0;
            } else {
                $baseInventario = round($totalFob / (1 + $iva), 2); // 87% aprox.
                $creditoFiscal = round($totalFob - $baseInventario, 2); // IVA compras 13%
            }

            // Compras internacionales se pagan a proveedores del exterior (FOB)
            $cuentaProveedor = $compra->tipo_compra === 'Internacional'
                ? self::cuenta('cuentas_por_pagar_ext')
                : self::cuenta('cuentas_por_pagar');

            $lineas = [
                ['cuenta' => self::cuenta('inventarios'), 'debe' => $baseInventario],
                ['cuenta' => self::cuenta('credito_fiscal'), 'debe' => $creditoFiscal],
                ['cuenta' => $cuentaProveedor, 'haber' => $totalFob],
            ];

            $glosa = "Asiento automático - Compra {$compra->codigo} ({$compra->tipo_compra})".
                ($compra->es_gnd ? ' [GND - sin crédito fiscal]' : '');

            return self::registrarAsiento($glosa, $lineas, 'compra', $compra->id);
        });
    }

    /**
     * Asiento de gasto de importación (PRD Módulo 3, paso I.P.): los costos
     * adicionales de prorrateo capitalizan al Inventario (mayor valor del activo).
     *
     * @return AsientoDiario|null Null si no hay costos adicionales.
     */
    public static function generarAsientoGastoImportacion(CompraCabecera $compra): ?AsientoDiario
    {
        if ((float) $compra->costos_adicionales_prorrateo <= 0) {
            return null;
        }

        return DB::transaction(function () use ($compra) {
            $monto = round((float) $compra->costos_adicionales_prorrateo, 2);

            // Capitalización: DEBE Inventarios | HABER Caja/Banco (pago de aranceles, fletes, estiba)
            return self::registrarAsiento(
                "Gasto de importación (I.P.) - Compra {$compra->codigo}: capitalización de costos al inventario",
                [
                    ['cuenta' => self::cuenta('inventarios'), 'debe' => $monto],
                    ['cuenta' => self::cuenta('banco'), 'haber' => $monto],
                ],
                'compra',
                $compra->id
            );
        });
    }

    /**
     * Asiento de pago a proveedor (PRD Módulo 3, paso final).
     */
    public static function generarAsientoPagoProveedor(CompraCabecera $compra): ?AsientoDiario
    {
        $total = round((float) $compra->total_final, 2);

        if ($total <= 0) {
            return null;
        }

        return DB::transaction(function () use ($compra, $total) {
            $cuentaProveedor = $compra->tipo_compra === 'Internacional'
                ? self::cuenta('cuentas_por_pagar_ext')
                : self::cuenta('cuentas_por_pagar');

            // DEBE Proveedores (cancela pasivo) | HABER Banco (sale el dinero)
            return self::registrarAsiento(
                "Pago a proveedor - Compra {$compra->codigo}",
                [
                    ['cuenta' => $cuentaProveedor, 'debe' => $total],
                    ['cuenta' => self::cuenta('banco'), 'haber' => $total],
                ],
                'compra',
                $compra->id
            );
        });
    }

    /**
     * Asiento de ajuste de inventario (PRD Módulo 4):
     *  - Donación: HABER Inventarios (egreso) o DEBE (ingreso), sin impacto en gastos.
     *  - Compra GND: DEBE Inventarios | HABER Caja (costo asignado directo).
     *  - Pérdida/Deterioro: DEBE Gasto No Deducible | HABER Inventarios.
     */
    public static function generarAsientoAjuste(AjusteInventario $ajuste): AsientoDiario
    {
        return DB::transaction(function () use ($ajuste) {
            $monto = round(abs($ajuste->cantidad) * (float) $ajuste->costo_unitario, 2);
            $esIngreso = $ajuste->cantidad > 0;

            $lineas = match ($ajuste->tipo) {
                // Donación ingresada: DEBE Inventarios | HABER Donaciones (Patrimonio/Ingreso).
                // El PRD indica que afecta stock SIN modificar costos promedio ni generar gasto.
                'Donacion' => [
                    ['cuenta' => self::cuenta('inventarios'), 'debe' => $esIngreso ? $monto : 0],
                    ['cuenta' => self::cuenta('capital'), 'haber' => $esIngreso ? $monto : 0],
                    ['cuenta' => self::cuenta('capital'), 'debe' => $esIngreso ? 0 : $monto],
                    ['cuenta' => self::cuenta('inventarios'), 'haber' => $esIngreso ? 0 : $monto],
                ],
                // Compra GND: DEBE Inventarios | HABER Caja (pago sin factura fiscal)
                'CompraGND' => [
                    ['cuenta' => self::cuenta('inventarios'), 'debe' => $esIngreso ? $monto : 0],
                    ['cuenta' => self::cuenta('caja_chica'), 'haber' => $esIngreso ? $monto : 0],
                    ['cuenta' => self::cuenta('caja_chica'), 'debe' => $esIngreso ? 0 : $monto],
                    ['cuenta' => self::cuenta('inventarios'), 'haber' => $esIngreso ? 0 : $monto],
                ],
                // Pérdida/Deterioro: DEBE Gasto No Deducible | HABER Inventarios
                default => [
                    ['cuenta' => self::cuenta('gastos_no_deducibles'), 'debe' => $monto],
                    ['cuenta' => self::cuenta('inventarios'), 'haber' => $monto],
                ],
            };

            $etiqueta = match ($ajuste->tipo) {
                'Donacion' => 'Ingreso por Donación',
                'CompraGND' => 'Ingreso por Compra GND',
                default => 'Egreso por Pérdida/Deterioro',
            };

            return self::registrarAsiento(
                "Ajuste de inventario [{$etiqueta}] - {$ajuste->codigo}",
                $lineas,
                'ajuste',
                $ajuste->id
            );
        });
    }

    /**
     * Asiento de cobro de crédito (PRD Módulo 2): DEBE Caja/Banco | HABER Cuentas por Cobrar.
     */
    public static function generarAsientoPagoCredito(PagoCliente $pago): AsientoDiario
    {
        return DB::transaction(function () use ($pago) {
            $monto = round((float) $pago->monto, 2);

            return self::registrarAsiento(
                "Cobro de crédito - Venta {$pago->venta->numero_recibo}",
                [
                    [
                        'cuenta' => $pago->metodo === 'QR' ? self::cuenta('banco') : self::cuenta('caja_general'),
                        'debe' => $monto,
                    ],
                    ['cuenta' => self::cuenta('cuentas_por_cobrar'), 'haber' => $monto],
                ],
                'credito',
                $pago->venta_id
            );
        });
    }

    /**
     * Libro Diario filtrado por rango de fechas (PRD Módulo 5: informe interactivo).
     *
     * @return \Illuminate\Support\Collection<int, AsientoDiario>
     */
    public static function libroDiario(?string $desde = null, ?string $hasta = null)
    {
        return AsientoDiario::query()
            ->with(['detalles'])
            ->when($desde, fn ($q) => $q->whereDate('fecha_asiento', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha_asiento', '<=', $hasta))
            ->orderBy('fecha_asiento')
            ->orderBy('id')
            ->get();
    }

    /**
     * Estado de Resultados dinámico preliminar (PRD Módulo 5).
     *
     * Estructura: Ventas netas - Costo de Ventas = Utilidad Bruta;
     * luego - Gastos (deducibles + no deducibles) = Utilidad Neta.
     *
     * @return array{ventas: float, costo_ventas: float, utilidad_bruta: float,
     *               gastos_deducibles: float, gastos_no_deducibles: float, utilidad_neta: float}
     */
    public static function estadoResultados(?string $desde = null, ?string $hasta = null): array
    {
        $movimientos = function (array $codigos) use ($desde, $hasta) {
            return DB::table('asiento_detalles')
                ->join('asientos_diario', 'asientos_diario.id', '=', 'asiento_detalles.asiento_diario_id')
                ->whereIn('cuenta_codigo', $codigos)
                ->when($desde, fn ($q) => $q->whereDate('asientos_diario.fecha_asiento', '>=', $desde))
                ->when($hasta, fn ($q) => $q->whereDate('asientos_diario.fecha_asiento', '<=', $hasta))
                ->selectRaw('COALESCE(SUM(asiento_detalles.debe - asiento_detalles.haber), 0) as saldo')
                ->value('saldo');
        };

        $ventas = -1 * (float) $movimientos([self::cuenta('ventas')]); // Ingreso: saldo acreedor
        $costoVentas = (float) $movimientos([self::cuenta('costo_ventas')]);
        $gastosDeducibles = (float) $movimientos([self::cuenta('gastos_deducibles')]);
        $gastosNoDeducibles = (float) $movimientos([self::cuenta('gastos_no_deducibles')]);

        $utilidadBruta = round($ventas - $costoVentas, 2);

        return [
            'ventas' => round($ventas, 2),
            'costo_ventas' => round($costoVentas, 2),
            'utilidad_bruta' => $utilidadBruta,
            'gastos_deducibles' => round($gastosDeducibles, 2),
            'gastos_no_deducibles' => round($gastosNoDeducibles, 2),
            'utilidad_neta' => round($utilidadBruta - $gastosDeducibles - $gastosNoDeducibles, 2),
        ];
    }
}
