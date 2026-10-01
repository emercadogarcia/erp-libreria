<?php

namespace Database\Seeders;

use App\Models\PlanCuenta;
use Illuminate\Database\Seeder;

/**
 * Plan de Cuentas Estándar parametrizado desde cero para el rubro librería
 * en Bolivia (PRD Módulo 5). Pre-cargado vía Seeder de Laravel.
 *
 * Estructura: 1 Activo, 2 Pasivo, 3 Patrimonio, 4 Ingresos, 5 Gastos.
 */
class PlanCuentasSeeder extends Seeder
{
    /**
     * Cuentas esenciales del plan: Caja Chica, Banco, Inventarios, Cuentas por
     * Cobrar, Cuentas por Pagar, Gastos Deducibles, Gastos No Deducibles,
     * Ingresos por Ventas, Débito Fiscal, Crédito Fiscal, entre otras.
     *
     * @var array<int, array{codigo:string, nombre:string, tipo:string, naturaleza:string, es_imputable?:bool}>
     */
    public const CUENTAS = [
        // ============================ 1. ACTIVO ============================
        ['codigo' => '1.1.1.01', 'nombre' => 'Caja General', 'tipo' => 'Activo', 'naturaleza' => 'Deudora'],
        ['codigo' => '1.1.1.02', 'nombre' => 'Caja Chica', 'tipo' => 'Activo', 'naturaleza' => 'Deudora'],
        ['codigo' => '1.1.2.01', 'nombre' => 'Banco Cuenta Corriente', 'tipo' => 'Activo', 'naturaleza' => 'Deudora'],
        ['codigo' => '1.1.3.01', 'nombre' => 'Cuentas por Cobrar Clientes', 'tipo' => 'Activo', 'naturaleza' => 'Deudora'],
        ['codigo' => '1.1.4.01', 'nombre' => 'Inventario de Mercadería (Libros y Escritorio)', 'tipo' => 'Activo', 'naturaleza' => 'Deudora'],
        ['codigo' => '1.1.5.01', 'nombre' => 'Crédito Fiscal IVA (Compras)', 'tipo' => 'Activo', 'naturaleza' => 'Deudora'],

        // ============================ 2. PASIVO ============================
        ['codigo' => '2.1.1.01', 'nombre' => 'Cuentas por Pagar Proveedores Nacionales', 'tipo' => 'Pasivo', 'naturaleza' => 'Acreedora'],
        ['codigo' => '2.1.2.01', 'nombre' => 'Cuentas por Pagar Proveedores Exteriores (FOB)', 'tipo' => 'Pasivo', 'naturaleza' => 'Acreedora'],
        ['codigo' => '2.1.3.01', 'nombre' => 'Débito Fiscal IVA (Ventas)', 'tipo' => 'Pasivo', 'naturaleza' => 'Acreedora'],
        ['codigo' => '2.1.4.01', 'nombre' => 'Transacciones por Pagar IT 3%', 'tipo' => 'Pasivo', 'naturaleza' => 'Acreedora'],

        // ========================== 3. PATRIMONIO ==========================
        ['codigo' => '3.1.1.01', 'nombre' => 'Capital Social / Donaciones Recibidas', 'tipo' => 'Patrimonio', 'naturaleza' => 'Acreedora'],

        // =========================== 4. INGRESOS ===========================
        ['codigo' => '4.1.1.01', 'nombre' => 'Ingresos por Ventas de Libros y Escritorio', 'tipo' => 'Ingreso', 'naturaleza' => 'Acreedora'],

        // ============================ 5. GASTOS ============================
        ['codigo' => '5.1.1.01', 'nombre' => 'Costo de Ventas', 'tipo' => 'Gasto', 'naturaleza' => 'Deudora'],
        ['codigo' => '5.2.1.01', 'nombre' => 'Gastos Deducibles (con factura fiscal)', 'tipo' => 'Gasto', 'naturaleza' => 'Deudora'],
        ['codigo' => '5.2.2.01', 'nombre' => 'Gastos No Deducibles (GND internos)', 'tipo' => 'Gasto', 'naturaleza' => 'Deudora'],
    ];

    /**
     * Ejecuta la carga del plan de cuentas (idempotente: updateOrCreate).
     */
    public function run(): void
    {
        foreach (self::CUENTAS as $cuenta) {
            PlanCuenta::updateOrCreate(
                ['codigo' => $cuenta['codigo']],
                [
                    'nombre' => $cuenta['nombre'],
                    'tipo' => $cuenta['tipo'],
                    'naturaleza' => $cuenta['naturaleza'],
                    'es_imputable' => true,
                ]
            );
        }
    }
}
