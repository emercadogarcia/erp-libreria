<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\CajaDiaria;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Exception;
use InvalidArgumentException;

/**
 * Servicio de ventas (PRD Módulos 1 y 2).
 *
 * Maneja el POS de mostrador, el checkout web anónimo con comprobante y la
 * aprobación manual de ventas web. Toda venta aprobada:
 *  1. Descuenta stock físico (transacción bloqueada).
 *  2. Congela el CMP histórico por línea para el costo de ventas.
 *  3. Genera su asiento contable automático (partida doble).
 */
class SalesService
{
    /**
     * Genera el correlativo de recibo secuencial.
     */
    public static function siguienteNumeroRecibo(string $prefijo = 'REC'): string
    {
        $correlativo = (int) Venta::max('id') + 1;

        return $prefijo.'-'.str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Registra una venta de mostrador (POS interno de Filament) ya aprobada.
     *
     * @param  array<int, array{articulo_id:int, cantidad:int}>  $items
     * @param  array{nombre?:string, nit_ci?:string, email?:string}  $clienteDatos
     * @param  string  $formaPago  Efectivo|QR|Credito
     * @param  int|null  $clienteId  Cliente registrado (obligatorio si es crédito).
     * @return Venta
     *
     * @throws Exception Si falla stock/contabilidad (rollback total).
     */
    public static function registrarVentaMostrador(
        array $items,
        array $clienteDatos = [],
        string $formaPago = 'Efectivo',
        ?int $clienteId = null,
        float $descuento = 0.0,
    ): Venta {
        return DB::transaction(function () use ($items, $clienteDatos, $formaPago, $clienteId, $descuento) {
            $venta = self::crearVentaConDetalles(
                items: $items,
                clienteId: $clienteId,
                clienteDatos: $clienteDatos,
                origen: 'mostrador',
                formaPago: $formaPago,
                descuento: $descuento,
            );

            // Venta de mostrador se consolida al instante (aprobada implícita)
            $venta->estado = 'Aprobada';
            $venta->aprobada_at = now();
            $venta->aprobada_por = auth()->id();
            $venta->es_credito = $formaPago === 'Credito';
            $venta->save();

            // Descuento de stock + asiento contable en la misma transacción
            self::descontarStockYContabilizar($venta);

            // Ventas al contado en efectivo alimentan la caja del día
            if ($formaPago === 'Efectivo') {
                self::registrarEnCaja($venta);
            }

            return $venta;
        });
    }

    /**
     * Registra un pedido web del catálogo público (PRD Módulo 1).
     * Nace en estado PendienteValidacion, sin afectar stock ni contabilidad.
     *
     * @param  array<int, array{articulo_id:int, cantidad:int}>  $items
     * @param  array{nombre:string, nit_ci:string, email:string}  $clienteDatos
     * @param  UploadedFile|null  $comprobante  Captura JPG/PNG de la transferencia.
     * @return Venta
     */
    public static function registrarVentaWeb(array $items, array $clienteDatos, ?UploadedFile $comprobante = null): Venta
    {
        return DB::transaction(function () use ($items, $clienteDatos, $comprobante) {
            $venta = self::crearVentaConDetalles(
                items: $items,
                clienteId: null,
                clienteDatos: $clienteDatos,
                origen: 'web',
                formaPago: 'QR',
                descuento: 0.0,
            );

            // Estado inicial del flujo web: Pendiente de Validación (PRD Módulo 1)
            $venta->estado = 'PendienteValidacion';
            $venta->save();

            // Almacenamiento del comprobante (JPG/PNG) si fue adjuntado
            if ($comprobante) {
                if (! in_array($comprobante->getClientOriginalExtension(), ['jpg', 'jpeg', 'png'], true)) {
                    throw new InvalidArgumentException('El comprobante debe ser un archivo JPG o PNG.');
                }

                $venta->comprobante_path = $comprobante->store('comprobantes', 'public');
                $venta->save();
            }

            return $venta;
        });
    }

    /**
     * Aprobación manual de una venta web (PRD Módulo 2) tras la revisión visual
     * del comprobante. Ejecuta stock + asiento contable en una sola transacción;
     * si el asiento falla, el inventario se revierte por completo (rollback).
     *
     * @throws Exception Si la venta no está pendiente o falla la consolidación.
     */
    public static function aprobarVenta(Venta $venta, ?int $userId = null): Venta
    {
        return DB::transaction(function () use ($venta, $userId) {
            $venta = Venta::lockForUpdate()->findOrFail($venta->id);

            if ($venta->estado !== 'PendienteValidacion') {
                throw new Exception("Solo se aprueban ventas pendientes de validación. Estado actual: {$venta->estado}");
            }

            $venta->estado = 'Aprobada';
            $venta->aprobada_at = now();
            $venta->aprobada_por = $userId ?? auth()->id();
            $venta->save();

            // Consolidación: stock + contabilidad (rollback si el asiento falla)
            self::descontarStockYContabilizar($venta);

            return $venta;
        });
    }

    /**
     * Rechaza una venta web pendiente (no afecta stock ni contabilidad).
     */
    public static function rechazarVenta(Venta $venta, string $motivo = ''): Venta
    {
        if ($venta->estado !== 'PendienteValidacion') {
            throw new Exception('Solo se rechazan ventas pendientes de validación.');
        }

        $venta->estado = 'Rechazada';
        $venta->save();

        return $venta;
    }

    /**
     * Registra un pago de crédito/cuota de un cliente (PRD Módulo 2).
     * Genera automáticamente su asiento de cobranza.
     *
     * @throws InvalidArgumentException Si el pago excede el saldo pendiente.
     */
    public static function registrarPagoCredito(Venta $venta, float $monto, string $metodo = 'Efectivo', string $observacion = ''): Venta
    {
        return DB::transaction(function () use ($venta, $monto, $metodo, $observacion) {
            $venta = Venta::lockForUpdate()->findOrFail($venta->id);

            if (! $venta->es_credito) {
                throw new InvalidArgumentException('La venta no es a crédito.');
            }

            $saldo = $venta->saldo_credito;

            if ($monto <= 0 || $monto > $saldo + 0.001) {
                throw new InvalidArgumentException("El monto excede el saldo pendiente (Bs. {$saldo}).");
            }

            $pago = $venta->pagos()->create([
                'cliente_id' => $venta->cliente_id,
                'monto' => round($monto, 2),
                'metodo' => $metodo,
                'observacion' => $observacion,
                'registrado_por' => auth()->id(),
            ]);

            // Asiento de cobranza: DEBE Caja/Banco | HABER Cuentas por Cobrar
            AccountingService::generarAsientoPagoCredito($pago);

            return $venta->fresh();
        });
    }

    /**
     * Apertura de caja diaria (PRD Módulo 2, montos en Bs.).
     *
     * @throws Exception Si ya existe una caja abierta/registrada para hoy.
     */
    public static function abrirCaja(float $montoApertura, ?int $userId = null): CajaDiaria
    {
        $hoy = now()->toDateString();

        if (CajaDiaria::whereDate('fecha', $hoy)->exists()) {
            throw new Exception('Ya existe una caja registrada para hoy.');
        }

        return CajaDiaria::create([
            'fecha' => $hoy,
            'user_id' => $userId ?? auth()->id(),
            'monto_apertura' => round($montoApertura, 2),
            'estado' => 'Abierta',
        ]);
    }

    /**
     * Cierre de caja diaria con arqueo (diferencia = cierre vs teórico).
     */
    public static function cerrarCaja(CajaDiaria $caja, float $montoCierre, string $observaciones = ''): CajaDiaria
    {
        if ($caja->estado === 'Cerrada') {
            throw new Exception('La caja ya está cerrada.');
        }

        $caja->monto_cierre = round($montoCierre, 2);
        $caja->diferencia = round($montoCierre - $caja->total_teorico, 2);
        $caja->estado = 'Cerrada';
        $caja->observaciones = $observaciones;
        $caja->save();

        return $caja;
    }

    /**
     * Registra la venta en la caja del día (efectivo a ventas_efectivo, QR a ventas_qr).
     */
    protected static function registrarEnCaja(Venta $venta): void
    {
        $caja = CajaDiaria::whereDate('fecha', now()->toDateString())->first();

        if (! $caja || $caja->estado === 'Cerrada') {
            return; // Sin caja abierta no se registra en arqueo (la venta queda válida)
        }

        if ($venta->forma_pago === 'Efectivo') {
            $caja->ventas_efectivo = round((float) $caja->ventas_efectivo + (float) $venta->total, 2);
        } elseif ($venta->forma_pago === 'QR') {
            $caja->ventas_qr = round((float) $caja->ventas_qr + (float) $venta->total, 2);
        }

        $caja->save();
    }

    /**
     * Crea la cabecera de venta + líneas con precios y CMP histórico congelado.
     *
     * @param  array<int, array{articulo_id:int, cantidad:int}>  $items
     * @param  array{nombre?:string, nit_ci?:string, email?:string}  $clienteDatos
     */
    protected static function crearVentaConDetalles(
        array $items,
        ?int $clienteId,
        array $clienteDatos,
        string $origen,
        string $formaPago,
        float $descuento = 0.0,
    ): Venta {
        if (empty($items)) {
            throw new InvalidArgumentException('La venta no tiene ítems.');
        }

        // Datos del cliente: registrado o del checkout web anónimo
        $cliente = $clienteId ? Cliente::findOrFail($clienteId) : null;
        $nombre = $cliente->nombre_razon_social ?? ($clienteDatos['nombre'] ?? 'Cliente Ocasional');
        $nitCi = $cliente->nit_ci ?? ($clienteDatos['nit_ci'] ?? '0');
        $email = $cliente->email ?? ($clienteDatos['email'] ?? null);

        $venta = Venta::create([
            'numero_recibo' => self::siguienteNumeroRecibo($origen === 'web' ? 'REC' : 'POS'),
            'cliente_id' => $clienteId,
            'cliente_nombre' => $nombre,
            'cliente_nit_ci' => $nitCi,
            'cliente_email' => $email,
            'origen' => $origen,
            'forma_pago' => $formaPago,
            'descuento' => round($descuento, 2),
        ]);

        foreach ($items as $item) {
            $articulo = Articulo::lockForUpdate()->findOrFail($item['articulo_id']);

            if ($articulo->stock_actual < $item['cantidad']) {
                throw new InvalidArgumentException(
                    "Stock insuficiente de «{$articulo->titulo_nombre}»: hay {$articulo->stock_actual}, se piden {$item['cantidad']}."
                );
            }

            $precio = (float) $articulo->precio_venta;

            $venta->detalles()->create([
                'articulo_id' => $articulo->id,
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $precio,
                'costo_unitario_historico' => (float) $articulo->costo_medio_ponderado,
                'subtotal' => round($precio * $item['cantidad'], 2),
            ]);
        }

        $subtotal = round((float) $venta->detalles()->sum('subtotal'), 2);
        $venta->subtotal = $subtotal;
        $venta->total = round($subtotal - $descuento, 2);
        $venta->save();

        return $venta;
    }

    /**
     * Consolida una venta: descuenta stock físico y genera el asiento contable
     * dentro de la MISMA transacción (PRD Sección 6: seguridad financiera).
     */
    protected static function descontarStockYContabilizar(Venta $venta): void
    {
        foreach ($venta->detalles as $detalle) {
            $articulo = Articulo::lockForUpdate()->findOrFail($detalle->articulo_id);

            if ($articulo->stock_actual < $detalle->cantidad) {
                throw new Exception(
                    "Stock insuficiente al consolidar «{$articulo->titulo_nombre}». Transacción abortada."
                );
            }

            $articulo->stock_actual -= $detalle->cantidad;
            $articulo->save();
        }

        // Asiento automático (se ejecuta dentro de la transacción del llamador)
        AccountingService::generarAsientoVenta($venta);
    }
}
