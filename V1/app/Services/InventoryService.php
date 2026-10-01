<?php

namespace App\Services;

use App\Models\AjusteInventario;
use App\Models\Articulo;
use Illuminate\Support\Facades\DB;
use Exception;
use InvalidArgumentException;

/**
 * Servicio de inventario y ecuación de costos (PRD Módulo 4).
 *
 * Responsabilidades aisladas (SRP):
 *  - Recalcular el Costo Medio Ponderado (CMP) tras ingresos de mercadería.
 *  - Recalcular el Precio de Venta (P.V.) con margen parametrizable.
 *  - Aplicar ajustes de inventario (Donación, Compra GND, Pérdida/Deterioro)
 *    dentro de transacciones seguras junto a su asiento contable.
 */
class InventoryService
{
    /**
     * Recalcula el CMP de un artículo tras un ingreso de mercadería.
     *
     * Ecuación del Costo Medio Ponderado:
     *   CMP = (Stock actual * CMP anterior + Cantidad ingresada * Costo unitario de ingreso)
     *         / (Stock actual + Cantidad ingresada)
     *
     * @param  Articulo  $articulo  Artículo a recalcular.
     * @param  int  $cantidadIngreso  Unidades que ingresan al stock.
     * @param  float  $costoUnitarioIngreso  Costo real unitario del ingreso (ya prorrateado si aplica).
     * @param  bool  $afectaCmp  false para donaciones (no alteran el costo promedio).
     * @return float Nuevo CMP redondeado a 4 decimales.
     */
    public static function recalcularCmp(Articulo $articulo, int $cantidadIngreso, float $costoUnitarioIngreso, bool $afectaCmp = true): float
    {
        // Si no afecta CMP (donaciones), el costo promedio se mantiene intacto
        if (! $afectaCmp) {
            return (float) $articulo->costo_medio_ponderado;
        }

        $stockAnterior = max(0, $articulo->stock_actual);
        $cmpAnterior = (float) $articulo->costo_medio_ponderado;

        $valorInventarioAnterior = $stockAnterior * $cmpAnterior;
        $valorIngreso = $cantidadIngreso * $costoUnitarioIngreso;
        $stockNuevo = $stockAnterior + $cantidadIngreso;

        if ($stockNuevo <= 0) {
            return round($cmpAnterior, 4);
        }

        return round(($valorInventarioAnterior + $valorIngreso) / $stockNuevo, 4);
    }

    /**
     * Recalcula automáticamente el Precio de Venta (P.V.) a partir del CMP y
     * el margen de ganancia parametrizable (config('accounting.margen_ganancia')).
     *
     * P.V. = CMP * (1 + margen), redondeado a 2 decimales para presentación.
     *
     * @return float Nuevo precio de venta.
     */
    public static function recalcularPrecioVenta(Articulo $articulo, ?float $margen = null): float
    {
        $margen = $margen ?? (float) config('accounting.margen_ganancia');
        $nuevoPrecio = round((float) $articulo->costo_medio_ponderado * (1 + $margen), 2);

        $articulo->forceFill(['precio_venta' => $nuevoPrecio])->save();

        return $nuevoPrecio;
    }

    /**
     * Registra un ajuste de inventario dentro de una transacción segura junto
     * a su asiento contable automático (PRD Módulo 4: Ingreso por Donación,
     * Ingreso por Compra GND, Egreso por Pérdida o Deterioro).
     *
     * Reglas:
     *  - Donacion: afecta stock, NO altera el CMP, costo_unitario = 0.
     *  - CompraGND: afecta stock, costo asignado directo, capitaliza al CMP.
     *  - PerdidaDeterioro: egresa stock al CMP vigente y genera gasto GND.
     *
     * @param  array{tipo:string, articulo_id:int, cantidad:int, costo_unitario?:float, motivo?:string}  $datos
     * @param  int|null  $userId  Usuario que registra el ajuste.
     * @return AjusteInventario
     *
     * @throws Exception Si falla la operación (rollback completo de stock y asiento).
     * @throws InvalidArgumentException Si los datos son incoherentes.
     */
    public static function registrarAjuste(array $datos, ?int $userId = null): AjusteInventario
    {
        // Toda mutación de stock + libro contable va envuelta en DB::transaction (PRD Sección 6)
        return DB::transaction(function () use ($datos, $userId) {
            $articulo = Articulo::lockForUpdate()->findOrFail($datos['articulo_id']);
            $cantidad = (int) $datos['cantidad'];

            if ($cantidad === 0) {
                throw new InvalidArgumentException('La cantidad del ajuste no puede ser cero.');
            }

            $tipo = $datos['tipo'];
            $costoUnitario = (float) ($datos['costo_unitario'] ?? 0);

            // Validaciones de coherencia por tipo de ajuste
            if ($tipo === 'Donacion' && $cantidad < 0) {
                throw new InvalidArgumentException('La donación solo admite ingresos de stock.');
            }

            if ($tipo === 'CompraGND' && ($cantidad < 0 || $costoUnitario <= 0)) {
                throw new InvalidArgumentException('La compra GND requiere cantidad positiva y costo asignado directo.');
            }

            if ($tipo === 'PerdidaDeterioro') {
                if ($cantidad > 0) {
                    throw new InvalidArgumentException('La pérdida/deterioro solo admite egresos de stock.');
                }
                if ($articulo->stock_actual < abs($cantidad)) {
                    throw new InvalidArgumentException(
                        "Stock insuficiente: hay {$articulo->stock_actual} unidades y se intentan egresar ".abs($cantidad).'.'
                    );
                }
            }

            $esIngreso = $cantidad > 0;

            // El CMP se recalcula solo si el ajuste altera costos de adquisición
            $afectaCmp = match ($tipo) {
                'Donacion' => false,                       // Donación: no modifica costos promedio (PRD)
                'CompraGND' => true,                       // GND: costo asignado directo capitaliza
                default => false,                          // Pérdida: egresa al CMP vigente
            };

            // Generación del correlativo del ajuste
            $codigo = 'AJU-'.str_pad((string) ((int) AjusteInventario::max('id') + 1), 6, '0', STR_PAD_LEFT);

            $ajuste = AjusteInventario::create([
                'codigo' => $codigo,
                'tipo' => $tipo,
                'articulo_id' => $articulo->id,
                'cantidad' => $cantidad,
                'costo_unitario' => match ($tipo) {
                    'Donacion' => 0.0,                     // Sin impacto económico
                    'CompraGND' => $costoUnitario,         // Costo asignado directo
                    default => (float) $articulo->costo_medio_ponderado, // CMP vigente
                },
                'afecta_cmp' => $afectaCmp,
                'motivo' => $datos['motivo'] ?? null,
                'user_id' => $userId ?? auth()->id(),
            ]);

            // 1. Recalculo del CMP ANTES de sumar el stock, de modo que la ecuación
            //    del promedio ponderado use el stock previo al ingreso
            if ($afectaCmp && $esIngreso) {
                $articulo->costo_medio_ponderado = self::recalcularCmp($articulo, $cantidad, $costoUnitario, true);
                // Automatización crítica del PRD: el P.V. se recalcula al alterar costos
                self::recalcularPrecioVenta($articulo);
            }

            // 2. Actualización del stock físico (una sola vez)
            $articulo->stock_actual += $cantidad;
            $articulo->save();

            // 3. Asiento contable automático (misma transacción).
            // Una donación sin valor asignado (costo_unitario = 0) solo mueve stock:
            // no genera asiento de monto cero (partida doble exige montos > 0).
            if (abs($ajuste->cantidad * (float) $ajuste->costo_unitario) > 0) {
                AccountingService::generarAsientoAjuste($ajuste);
            }

            return $ajuste;
        });
    }

    /**
     * Kardex simplificado del artículo: movimientos de compra, venta y ajustes.
     *
     * @return array<int, array{fecha:string, tipo:string, documento:string, entrada:int, salida:int, saldo:int}>
     */
    public static function kardex(Articulo $articulo): array
    {
        $movimientos = [];

        foreach ($articulo->compraDetalles()->with('compra')->orderBy('created_at')->get() as $detalle) {
            $movimientos[] = [
                'fecha' => optional($detalle->compra->fecha_emision)->format('d/m/Y') ?? '',
                'tipo' => 'Ingreso por compra',
                'documento' => $detalle->compra->codigo,
                'entrada' => $detalle->cantidad,
                'salida' => 0,
                'saldo' => null,
            ];
        }

        foreach ($articulo->ventaDetalles()->with('venta')->orderBy('created_at')->get() as $detalle) {
            $movimientos[] = [
                'fecha' => optional($detalle->venta->created_at)->format('d/m/Y') ?? '',
                'tipo' => 'Salida por venta',
                'documento' => $detalle->venta->numero_recibo,
                'entrada' => 0,
                'salida' => $detalle->cantidad,
                'saldo' => null,
            ];
        }

        foreach ($articulo->ajustes()->orderBy('created_at')->get() as $ajuste) {
            $movimientos[] = [
                'fecha' => optional($ajuste->created_at)->format('d/m/Y') ?? '',
                'tipo' => match ($ajuste->tipo) {
                    'Donacion' => 'Ingreso por donación',
                    'CompraGND' => 'Ingreso por compra GND',
                    default => 'Egreso por pérdida/deterioro',
                },
                'documento' => $ajuste->codigo,
                'entrada' => max(0, $ajuste->cantidad),
                'salida' => max(0, -$ajuste->cantidad),
                'saldo' => null,
            ];
        }

        // Orden cronológico y cálculo de saldo acumulado
        usort($movimientos, fn ($a, $b) => strcmp($a['fecha'], $b['fecha']));

        $saldo = 0;
        foreach ($movimientos as &$mov) {
            $saldo += $mov['entrada'] - $mov['salida'];
            $mov['saldo'] = $saldo;
        }

        return $movimientos;
    }
}
