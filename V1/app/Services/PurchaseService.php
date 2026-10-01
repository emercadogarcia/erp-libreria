<?php

namespace App\Services;

use App\Models\CompraCabecera;
use App\Models\CompraDetalle;
use App\Models\Articulo;
use Illuminate\Support\Facades\DB;
use Exception;
use InvalidArgumentException;

/**
 * Servicio del flujo secuencial de compras (PRD Módulo 3).
 *
 * Estados inalterables: Cotizacion -> Oferta -> OrdenCompra -> NotaIngreso ->
 * Facturado -> GastoImportacion -> Pagado.
 *
 * El prorrateo suma los costos adicionales al valor de la factura del proveedor
 * (F.fob) para recalcular automáticamente el costo real unitario que ingresa a
 * inventario, y ejecuta el recálculo del CMP y del P.V. en segundo plano.
 */
class PurchaseService
{
    /**
     * Avanza el flujo secuencial al siguiente estado (inalterable, sin saltos).
     *
     * @param  CompraCabecera  $compra  Compra a avanzar.
     * @return CompraCabecera
     *
     * @throws Exception Si ya está en el estado final (Pagado).
     */
    public static function avanzarFlujo(CompraCabecera $compra): CompraCabecera
    {
        $indiceActual = array_search($compra->estado_flujo, CompraCabecera::ESTADOS, true);

        if ($indiceActual === false) {
            throw new Exception("Estado de flujo inválido: {$compra->estado_flujo}");
        }

        if ($indiceActual >= count(CompraCabecera::ESTADOS) - 1) {
            throw new Exception('La compra ya está en el estado final (Pagado).');
        }

        $compra->estado_flujo = CompraCabecera::ESTADOS[$indiceActual + 1];
        $compra->save();

        return $compra;
    }

    /**
     * Registra la factura de proveedor (F.fob) con el valor FOB de la compra.
     *
     * @param  CompraCabecera  $compra
     * @param  float  $valorFob  Valor total FOB de la factura (o de compra local).
     */
    public static function registrarFacturaFob(CompraCabecera $compra, float $valorFob): CompraCabecera
    {
        $compra->valor_fob = round($valorFob, 2);
        $compra->save();

        return $compra;
    }

    /**
     * Aplica el prorrateo de costos adicionales (aranceles, fletes, estiba) y
     * recalcula automáticamente el costo real unitario de cada línea.
     *
     * Método de reparto: proporcional al valor FOB de cada línea, de forma que
     * el costo final total de líneas + costos adicionales cuadre exactamente.
     *
     * Efectos (todo dentro de una transacción):
     *  1. Recalcula costo_unitario_final de cada línea.
     *  2. Ejecuta el recálculo del CMP y del P.V. de cada artículo ingresado.
     *  3. Registra el asiento de gasto de importación (I.P.).
     *
     * @param  CompraCabecera  $compra  Compra en estado Facturado con FOB ya registrado.
     * @param  float  $costosAdicionales  Total de aranceles + fletes + transportes.
     * @return CompraCabecera
     *
     * @throws Exception Si el estado no permite prorrateo o el rollback es necesario.
     */
    public static function aplicarProrrateo(CompraCabecera $compra, float $costosAdicionales): CompraCabecera
    {
        // Flujo secuencial e inalterable: el prorrateo solo aplica a compras facturadas
        if ($compra->estado_flujo !== 'Facturado' && $compra->estado_flujo !== 'NotaIngreso') {
            throw new InvalidArgumentException(
                "El prorrateo solo aplica a compras facturadas. Estado actual: {$compra->estado_flujo}"
            );
        }

        if ($costosAdicionales < 0) {
            throw new InvalidArgumentException('Los costos adicionales no pueden ser negativos.');
        }

        return DB::transaction(function () use ($compra, $costosAdicionales) {
            $compra = CompraCabecera::lockForUpdate()->with('detalles')->findOrFail($compra->id);

            $totalFob = round((float) $compra->total_fob, 2);

            if ($totalFob <= 0) {
                throw new InvalidArgumentException('La compra no tiene valor FOB registrado; registre la factura F.fob primero.');
            }

            $compra->costos_adicionales_prorrateo = round($costosAdicionales, 2);

            // ------------------------------------------------------------------
            // PRORRATEO: reparto proporcional al valor FOB de cada línea
            // ------------------------------------------------------------------
            $costoAcumulado = 0.0;
            $lineas = $compra->detalles;

            foreach ($lineas as $indice => $linea) {
                $valorLinea = $linea->cantidad * (float) $linea->costo_unitario_fob;
                $proporcion = $valorLinea / $totalFob;
                $costoAdicionalLinea = round($costosAdicionales * $proporcion, 4);

                // Última línea absorbe el residuo de redondeo para cuadrar el total
                if ($indice === count($lineas) - 1) {
                    $costoAdicionalLinea = round($costosAdicionales - $costoAcumulado, 4);
                }

                $costoAcumulado += $costoAdicionalLinea;

                $linea->costo_unitario_final = round((float) $linea->costo_unitario_fob + ($costoAdicionalLinea / $linea->cantidad), 4);
                $linea->subtotal = round($linea->cantidad * $linea->costo_unitario_final, 2);
                $linea->save();

                // ------------------------------------------------------------------
                // Automatización crítica del PRD: tras finalizar la E.M. con
                // prorrateo, recálculo del CMP + P.V. de cada artículo ingresado.
                //
                // El ingreso (E.M.) ya sumó la cantidad al stock, por lo que aquí
                // NO se vuelve a sumar cantidad: el prorrateo es una REVALUACIÓN
                // del inventario. Solo se incorpora la diferencia de costo
                // (costo final - costo FOB) sobre el stock vigente:
                //   CMP = (Stock * CMP actual + Cantidad * (Costo final - Costo FOB)) / Stock
                // ------------------------------------------------------------------
                $articulo = Articulo::lockForUpdate()->findOrFail($linea->articulo_id);
                $diferenciaCosto = round((float) $linea->costo_unitario_final - (float) $linea->costo_unitario_fob, 4);
                $stockVigente = max(1, $articulo->stock_actual);
                $valorAjustado = (float) $articulo->costo_medio_ponderado * $articulo->stock_actual + $linea->cantidad * $diferenciaCosto;
                $articulo->costo_medio_ponderado = round($valorAjustado / $stockVigente, 4);
                InventoryService::recalcularPrecioVenta($articulo);
            }

            $compra->save();

            // Asiento del gasto de importación (I.P.) - misma transacción
            if ($costosAdicionales > 0) {
                AccountingService::generarAsientoGastoImportacion($compra);
            }

            return $compra;
        });
    }

    /**
     * Registra el ingreso de mercadería a inventario (Nota de Ingreso / E.M.)
     * dentro de una transacción: sube stock y contabiliza la compra.
     *
     * @throws Exception Si falla el registro (rollback completo).
     */
    public static function registrarIngresoMercaderia(CompraCabecera $compra): CompraCabecera
    {
        return DB::transaction(function () use ($compra) {
            $compra = CompraCabecera::lockForUpdate()->with('detalles')->findOrFail($compra->id);

            if ($compra->estado_flujo !== 'OrdenCompra') {
                throw new InvalidArgumentException(
                    "El ingreso de mercadería solo aplica desde Orden de Compra. Estado actual: {$compra->estado_flujo}"
                );
            }

            foreach ($compra->detalles as $linea) {
                $articulo = Articulo::lockForUpdate()->findOrFail($linea->articulo_id);

                // Ingreso provisional al CMP con el costo FOB unitario (luego el prorrateo lo corrige)
                $articulo->stock_actual += $linea->cantidad;
                $nuevoCmp = InventoryService::recalcularCmp($articulo, $linea->cantidad, (float) $linea->costo_unitario_fob, !$compra->es_gnd);
                $articulo->costo_medio_ponderado = $nuevoCmp;
                InventoryService::recalcularPrecioVenta($articulo);
                $articulo->save();
            }

            // Asiento de compra (partida doble, separa crédito fiscal o GND)
            AccountingService::generarAsientoCompra($compra);

            $compra->estado_flujo = 'NotaIngreso';
            $compra->save();

            return $compra;
        });
    }

    /**
     * Registra el pago al proveedor (estado final del flujo).
     *
     * @throws Exception Si ya está pagada o el estado no corresponde.
     */
    public static function registrarPagoProveedor(CompraCabecera $compra): CompraCabecera
    {
        return DB::transaction(function () use ($compra) {
            $compra = CompraCabecera::lockForUpdate()->findOrFail($compra->id);

            if (! in_array($compra->estado_flujo, ['GastoImportacion', 'Facturado', 'NotaIngreso'], true)) {
                throw new InvalidArgumentException(
                    "El pago a proveedor aplica tras la facturación. Estado actual: {$compra->estado_flujo}"
                );
            }

            AccountingService::generarAsientoPagoProveedor($compra);

            $compra->estado_flujo = 'Pagado';
            $compra->save();

            return $compra;
        });
    }
}
