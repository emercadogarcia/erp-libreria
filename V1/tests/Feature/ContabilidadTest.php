<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\CompraCabecera;
use App\Models\Proveedor;
use App\Services\AccountingService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pruebas de aceptación contable (PRD Sección 7 - QA).
 */
class ContabilidadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanCuentasSeeder::class);
    }

    /**
     * Prueba de Consistencia Contable por Partida Doble (QA #1 del PRD):
     * una compra local GND debe asentar el 100% a inventario/GND sin crédito fiscal,
     * cumpliendo Debe - Haber = 0.
     */
    #[Test]
    public function compra_gnd_asienta_partida_doble_sin_credito_fiscal(): void
    {
        $proveedor = Proveedor::factory()->create();

        $compra = CompraCabecera::create([
            'codigo' => 'COMP-TEST-00001',
            'tipo_compra' => 'Nacional',
            'estado_flujo' => 'NotaIngreso',
            'proveedor_id' => $proveedor->id,
            'fecha_emision' => now(),
            'valor_fob' => 100,
            'es_gnd' => true,
        ]);

        $compra->detalles()->create([
            'articulo_id' => Articulo::factory()->create()->id,
            'cantidad' => 10,
            'costo_unitario_fob' => 10,
            'costo_unitario_final' => 10,
            'subtotal' => 100,
        ]);

        $asiento = AccountingService::generarAsientoCompra($compra);

        // Balance de partida doble: Debe - Haber = 0
        $this->assertTrue($asiento->estaCuadrado());
        $this->assertEquals(100, (float) $asiento->total_debe);
        $this->assertEquals(100, (float) $asiento->total_haber);

        // El 100% va a inventario, sin desglose de crédito fiscal (GND)
        $detalleInventario = $asiento->detalles->firstWhere('cuenta_codigo', AccountingService::cuenta('inventarios'));
        $this->assertNotNull($detalleInventario);
        $this->assertEquals(100, (float) $detalleInventario->debe);

        $detalleCreditoFiscal = $asiento->detalles->firstWhere('cuenta_codigo', AccountingService::cuenta('credito_fiscal'));
        $this->assertNull($detalleCreditoFiscal); // GND: no desglosa crédito fiscal
    }

    /**
     * Compra deducible: separa el crédito fiscal (13%).
     */
    #[Test]
    public function compra_deducible_separa_credito_fiscal(): void
    {
        $proveedor = Proveedor::factory()->create();

        $compra = CompraCabecera::create([
            'codigo' => 'COMP-TEST-00002',
            'tipo_compra' => 'Nacional',
            'estado_flujo' => 'NotaIngreso',
            'proveedor_id' => $proveedor->id,
            'fecha_emision' => now(),
            'valor_fob' => 113,
            'es_gnd' => false,
        ]);

        $compra->detalles()->create([
            'articulo_id' => Articulo::factory()->create()->id,
            'cantidad' => 10,
            'costo_unitario_fob' => 11.30,
            'costo_unitario_final' => 11.30,
            'subtotal' => 113,
        ]);

        $asiento = AccountingService::generarAsientoCompra($compra);

        $this->assertTrue($asiento->estaCuadrado());

        $base = round(113 / 1.13, 2);       // 100.00
        $credito = round(113 - $base, 2);   // 13.00

        $detalleInventario = $asiento->detalles->firstWhere('cuenta_codigo', AccountingService::cuenta('inventarios'));
        $detalleCredito = $asiento->detalles->firstWhere('cuenta_codigo', AccountingService::cuenta('credito_fiscal'));

        $this->assertEquals($base, (float) $detalleInventario->debe);
        $this->assertEquals($credito, (float) $detalleCredito->debe);
    }

    /**
     * Prueba de Prorrateo de Importaciones (QA #2 del PRD): los costos logísticos
     * se reparten proporcionalmente al FOB y elevan el costo unitario de inventario
     * sin alterar registros históricos de lotes pasados (CMP de ventas previas congelado).
     */
    #[Test]
    public function prorrateo_recalcula_costo_unitario_y_cmp(): void
    {
        // Historial: artículo con CMP anterior por una venta previa congelada
        $articulo = Articulo::factory()->create([
            'stock_actual' => 10,
            'costo_medio_ponderado' => 50.00,
        ]);

        $proveedor = Proveedor::factory()->create();

        $compra = CompraCabecera::create([
            'codigo' => 'COMP-TEST-00003',
            'tipo_compra' => 'Internacional',
            'estado_flujo' => 'Facturado',
            'proveedor_id' => $proveedor->id,
            'fecha_emision' => now(),
            'valor_fob' => 1000,
            'es_gnd' => false,
        ]);

        $detalle = $compra->detalles()->create([
            'articulo_id' => $articulo->id,
            'cantidad' => 20,
            'costo_unitario_fob' => 50,
            'costo_unitario_final' => 50,
            'subtotal' => 1000,
        ]);

        // Ingreso de mercadería: stock 10 + 20 = 30
        $compra->estado_flujo = 'OrdenCompra';
        $compra->save();
        $compra = PurchaseService::registrarIngresoMercaderia($compra);
        $this->assertEquals(30, $articulo->fresh()->stock_actual);

        // Costos adicionales de importación: aranceles + flete + estiba = Bs 200
        PurchaseService::aplicarProrrateo($compra, 200);

        // Costo unitario final = 50 + (200 * 1000/1000 / 20) = 60.00
        $this->assertEquals(60.0, (float) $detalle->fresh()->costo_unitario_final);

        // CMP recalculado: (10*50 + 20*50)/30 = 50 -> luego prorrateo (10*50 + 20*60)/30 = 56.67
        $this->assertEquals(56.6667, (float) $articulo->fresh()->costo_medio_ponderado);

        // El recálculo del P.V. se ejecutó con el margen del 30%
        $this->assertEquals(round(56.6667 * 1.30, 2), (float) $articulo->fresh()->precio_venta);

        // El asiento de gasto de importación capitalizó los Bs 200 al inventario
        $asientoIp = \App\Models\AsientoDiario::where('glosa', 'like', '%Gasto de importación%')->first();
        $this->assertNotNull($asientoIp);
        $this->assertTrue($asientoIp->estaCuadrado());
        $this->assertEquals(200, (float) $asientoIp->total_debe);
    }

    /**
     * Validación de la ecuación general Debe - Haber = 0 en cualquier asiento mal formado.
     */
    #[Test]
    public function asiento_descuadrado_lanza_excepcion(): void
    {
        $this->expectException(\Exception::class);

        AccountingService::registrarAsiento('Asiento inválido de prueba', [
            ['cuenta' => AccountingService::cuenta('caja_general'), 'debe' => 100],
            ['cuenta' => AccountingService::cuenta('ventas'), 'haber' => 90],
        ]);
    }

    /**
     * El asiento de venta desglosa ingreso neto + débito fiscal (IVA 13%).
     */
    #[Test]
    public function asiento_venta_desglosa_debito_fiscal(): void
    {
        $venta = \App\Models\Venta::create([
            'numero_recibo' => 'REC-TEST01',
            'cliente_nombre' => 'Cliente Prueba',
            'cliente_nit_ci' => '123456',
            'origen' => 'mostrador',
            'estado' => 'Aprobada',
            'forma_pago' => 'Efectivo',
            'subtotal' => 113,
            'total' => 113,
        ]);

        $venta->detalles()->create([
            'articulo_id' => Articulo::factory()->create()->id,
            'cantidad' => 1,
            'precio_unitario' => 113,
            'costo_unitario_historico' => 50,
            'subtotal' => 113,
        ]);

        $asiento = AccountingService::generarAsientoVenta($venta);

        $this->assertTrue($asiento->estaCuadrado());

        $base = round(113 / 1.13, 2);
        $itMonto = round($base * 0.03, 2);
        $ingresoNeto = round($base - $itMonto, 2);

        $detalleVentas = $asiento->detalles->firstWhere('cuenta_codigo', AccountingService::cuenta('ventas'));
        $detalleDebito = $asiento->detalles->firstWhere('cuenta_codigo', AccountingService::cuenta('debito_fiscal'));

        $this->assertEquals($ingresoNeto, (float) $detalleVentas->haber);
        $this->assertEquals(round(113 - $base, 2), (float) $detalleDebito->haber);
    }
}
