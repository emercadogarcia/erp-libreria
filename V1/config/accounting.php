<?php

/**
 * Configuración contable y fiscal del MVP (Ley 453 / Normas del SIN - Bolivia).
 *
 * El PRD exige un Plan de Cuentas Estándar parametrizado desde cero para el
 * rubro librería en Bolivia. Cada proceso de negocio referencia las cuentas
 * por su clave semántica (nunca por ID duro), de modo que el plan pueda
 * migrarse de versión sin romper los servicios contables.
 */
return [

    // Moneda oficial y precisión de presentación (decimal 14,2 solo en vistas/facturas)
    'moneda' => 'Bs',
    'decimales' => 2,

    // IVA simulado del 13% (Débito/Crédito Fiscal) exigido por el PRD
    'iva' => 0.13,

    // IT (Impuesto a las Transacciones) 3% del precio neto, para el asiento compuesto
    'it' => 0.03,

    // Margen de ganancia parametrizable usado por InventoryService para recalcular el P.V.
    'margen_ganancia' => 0.30, // 30% sobre el Costo Medio Ponderado

    // Cuentas clave del Plan de Cuentas (Seeder: PlanCuentasSeeder)
    'cuentas' => [
        'caja_general'        => '1.1.1.01', // Caja (Ventas de mostrador, pagos de créditos)
        'banco'               => '1.1.2.01', // Banco (pagos por QR / transferencia)
        'caja_chica'          => '1.1.1.02',
        'inventarios'         => '1.1.4.01', // Inventario de mercadería
        'cuentas_por_cobrar'  => '1.1.3.01', // Ventas a crédito
        'credito_fiscal'      => '1.1.5.01', // IVA compras (crédito fiscal 13%)
        'cuentas_por_pagar'   => '2.1.1.01', // Proveedores nacionales
        'cuentas_por_pagar_ext' => '2.1.2.01', // Proveedores internacionales (FOB)
        'capital'             => '3.1.1.01',
        'ventas'              => '4.1.1.01', // Ingresos por ventas (87% neto)
        'costo_ventas'        => '5.1.1.01', // Costo de ventas (CMP)
        'gastos_deducibles'   => '5.2.1.01', // Compras con factura fiscal
        'gastos_no_deducibles' => '5.2.2.01', // GND internos (sin factura fiscal)
        'debito_fiscal'       => '2.1.3.01', // IVA ventas (débito fiscal 13%)
        'transacciones_it'    => '2.1.4.01', // IT 3% por pagar
    ],

    // Datos de la empresa para el Recibo Informativo de Venta (Módulo 6)
    'empresa' => [
        'nombre' => 'Librería El Ateneo Bolivia S.R.L.',
        'nit' => '1025874015',
        'direccion' => 'Av. Mariscal Santa Cruz #1123, Zona Central',
        'ciudad' => 'La Paz - Bolivia',
        'telefono' => '+591 2 2314567',
        'email' => 'ventas@libreria-ateneo.bo',
        'cuis' => env('SIAT_CUIS', 'CUIS-SIM-2A7B9C31'),
        'cufd' => env('SIAT_CUFD', 'CUFD-SIM-A6F4E92D01BD7C53'),
        'punto_venta' => 'Punto de Venta 0 (Sala de Venta)',
        'sucursal' => 'Sucursal 0 (Casa Matriz)',
        'cuenta_bancaria' => 'Banco Unión Cte. 1000004587 - Librería El Ateneo S.R.L.',
        'telefono_qr' => '+591 70012345',
    ],

    // Leyendas obligatorias que acompañan a todo recibo/factura (Ley 453 Art. 14)
    'leyendas' => [
        'LEY-453' => '«La Ley N° 453 obliga a los proveedores a exhibir su certificado de habilitación y entregar facturas en todos sus sistemas de cobro.»',
        'FACTURA-SIMULADA' => 'RECIBO INFORMATIVO - DOCUMENTO SIMULADO SIN VALOR FISCAL (MVP: no conectado al SIN).',
    ],
];
