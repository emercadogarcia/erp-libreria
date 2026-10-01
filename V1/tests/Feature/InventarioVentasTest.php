<?php

namespace Tests\Feature;

use App\Models\Articulo;
use App\Models\User;
use App\Models\Venta;
use App\Services\InventoryService;
use App\Services\InvoiceService;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pruebas de inventario, ventas y checkout web (PRD Sección 7 - QA #3
 * + Módulos 1, 2, 4 y 6).
 */
class InventarioVentasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanCuentasSeeder::class);

        // Usuario con ID 1 para las claves foráneas de auditoría (user_id)
        User::factory()->create();
    }

    /**
     * Donación: afecta stock SIN modificar el CMP (PRD Módulo 4).
     */
    #[Test]
    public function donacion_afecta_stock_sin_alterar_cmp(): void
    {
        $articulo = Articulo::factory()->create([
            'stock_actual' => 10,
            'costo_medio_ponderado' => 40,
        ]);

        InventoryService::registrarAjuste([
            'tipo' => 'Donacion',
            'articulo_id' => $articulo->id,
            'cantidad' => 5,
            'motivo' => 'Donación editorial',
        ], userId: 1);

        $this->assertEquals(15, $articulo->fresh()->stock_actual);
        $this->assertEquals(40, (float) $articulo->fresh()->costo_medio_ponderado); // CMP intacto
    }

    /**
     * Compra GND por ajuste: costo asignado directo y capitaliza al CMP.
     */
    #[Test]
    public function compra_gnd_asigna_costo_directo_y_actualiza_cmp(): void
    {
        $articulo = Articulo::factory()->create([
            'stock_actual' => 10,
            'costo_medio_ponderado' => 40,
        ]);

        InventoryService::registrarAjuste([
            'tipo' => 'CompraGND',
            'articulo_id' => $articulo->id,
            'cantidad' => 5,
            'costo_unitario' => 20,
        ], userId: 1);

        // CMP: (10*40 + 5*20) / 15 = 33.33
        $this->assertEquals(33.3333, (float) $articulo->fresh()->costo_medio_ponderado);
        $this->assertEquals(15, $articulo->fresh()->stock_actual);
    }

    /**
     * Pérdida/deterioro: egresa stock y genera gasto GND contable.
     */
    #[Test]
    public function perdida_egresa_stock_y_genera_gasto(): void
    {
        $articulo = Articulo::factory()->create([
            'stock_actual' => 10,
            'costo_medio_ponderado' => 40,
        ]);

        $ajuste = InventoryService::registrarAjuste([
            'tipo' => 'PerdidaDeterioro',
            'articulo_id' => $articulo->id,
            'cantidad' => -3,
            'motivo' => 'Libros dañados por humedad',
        ], userId: 1);

        $this->assertEquals(7, $articulo->fresh()->stock_actual);

        // Asiento: DEBE GND | HABER Inventarios por 3*40 = 120
        $asiento = \App\Models\AsientoDiario::where('glosa', 'like', '%Pérdida/Deterioro%')->latest('id')->first();
        $this->assertNotNull($asiento);
        $this->assertTrue($asiento->estaCuadrado());
        $this->assertEquals(120, (float) $asiento->total_debe);
    }

    /**
     * Prueba de Checkout e Interfaz de Usuario Externa (QA #3 del PRD):
     * un usuario anónimo completa el flujo de compra (dos libros, NIT/Razón
     * Social, QR estático, comprobante JPG) sin errores HTTP 500.
     */
    #[Test]
    public function checkout_web_anonimo_completa_flujo_sin_errores(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $libro1 = Articulo::factory()->create(['stock_actual' => 10, 'precio_venta' => 100]);
        $libro2 = Articulo::factory()->create(['stock_actual' => 5, 'precio_venta' => 50]);

        // 1. El catálogo público responde sin errores
        $this->get('/')->assertOk()->assertSee($libro1->titulo_nombre);

        // 2. Registro de la venta web con dos ítems + datos del cliente + comprobante JPG
        $venta = SalesService::registrarVentaWeb(
            items: [
                ['articulo_id' => $libro1->id, 'cantidad' => 1],
                ['articulo_id' => $libro2->id, 'cantidad' => 2],
            ],
            clienteDatos: ['nombre' => 'María González', 'nit_ci' => '6345872', 'email' => 'maria@mail.com'],
            comprobante: UploadedFile::fake()->image('comprobante.png'),
        );

        // Sin errores 500: la venta queda pendiente con su comprobante adjunto
        $this->assertEquals('PendienteValidacion', $venta->estado);
        $this->assertNotNull($venta->comprobante_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($venta->comprobante_path);

        // 3. Aprobación admin + recibo imprimible (HTTP 200)
        SalesService::aprobarVenta($venta);

        $this->get(route('recibo.print', $venta))->assertOk()
            ->assertSee('RECIBO INFORMATIVO DE VENTA')
            ->assertSee($venta->numero_recibo);
    }

    /**
     * Flujo de aprobación web: nace PendienteValidacion, la aprobación descuenta
     * stock y contabiliza; el rechazo no toca stock ni contabilidad.
     */
    #[Test]
    public function venta_web_flujo_de_aprobacion(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $articulo = Articulo::factory()->create(['stock_actual' => 10, 'precio_venta' => 100]);

        $venta = SalesService::registrarVentaWeb(
            items: [['articulo_id' => $articulo->id, 'cantidad' => 2]],
            clienteDatos: ['nombre' => 'Juan Pérez', 'nit_ci' => '3389145', 'email' => 'jperez@mail.com'],
            comprobante: UploadedFile::fake()->image('comprobante.jpg'),
        );

        // Nace PendienteValidacion con comprobante adjunto, sin afectar stock
        $this->assertEquals('PendienteValidacion', $venta->estado);
        $this->assertNotNull($venta->comprobante_path);
        $this->assertEquals(10, $articulo->fresh()->stock_actual);
        $this->assertEquals(0, \App\Models\AsientoDiario::count());

        // Aprobación: stock + asiento contable
        $admin = User::factory()->create();
        SalesService::aprobarVenta($venta, $admin->id);

        $this->assertEquals('Aprobada', $venta->fresh()->estado);
        $this->assertEquals(8, $articulo->fresh()->stock_actual);
        $this->assertTrue(\App\Models\AsientoDiario::where('origen', 'venta')->exists());

        // Factura simulada con campos del SIN
        $factura = InvoiceService::emitirFactura($venta->fresh());
        $this->assertStringStartsWith('FAC-', $factura->numero_factura);
        $this->assertStringStartsWith('CUFD-SIM-', $factura->cufd);
        $this->assertNotEmpty($factura->codigo_control);
        $this->assertStringContainsString('Ley N° 453', $factura->leyenda);
    }

    /**
     * Venta de mostrador (POS): descuenta stock, contabiliza y alimenta caja.
     */
    #[Test]
    public function venta_mostrador_pos_descuenta_stock_y_contabiliza(): void
    {
        $articulo = Articulo::factory()->create(['stock_actual' => 10, 'precio_venta' => 50]);

        SalesService::abrirCaja(100, userId: 1);

        $venta = SalesService::registrarVentaMostrador(
            items: [['articulo_id' => $articulo->id, 'cantidad' => 3]],
            formaPago: 'Efectivo',
        );

        $this->assertEquals('Aprobada', $venta->estado);
        $this->assertEquals(7, $articulo->fresh()->stock_actual);
        $this->assertTrue(\App\Models\AsientoDiario::where('origen', 'venta')->exists());

        // Caja del día registró la venta en efectivo
        $caja = \App\Models\CajaDiaria::whereDate('fecha', today())->first();
        $this->assertNotNull($caja);
        $this->assertEquals(150, (float) $caja->ventas_efectivo);
    }

    /**
     * Créditos: pago de cuota genera asiento de cobranza y reduce saldo.
     */
    #[Test]
    public function pago_de_credito_reduce_saldo_y_contabiliza(): void
    {
        $cliente = \App\Models\Cliente::factory()->create();
        $articulo = Articulo::factory()->create(['stock_actual' => 10, 'precio_venta' => 100]);

        $venta = SalesService::registrarVentaMostrador(
            items: [['articulo_id' => $articulo->id, 'cantidad' => 1]],
            formaPago: 'Credito',
            clienteId: $cliente->id,
        );

        $this->assertTrue($venta->es_credito);
        $this->assertEquals(100, $venta->saldo_credito);

        SalesService::registrarPagoCredito($venta, 40, 'Efectivo', 'Primera cuota');

        $this->assertEquals(60, $venta->fresh()->saldo_credito);
        $this->assertTrue(\App\Models\AsientoDiario::where('origen', 'credito')->exists());
    }

    /**
     * No se puede vender más stock del disponible (integridad operativa).
     */
    #[Test]
    public function venta_con_stock_insuficiente_falla(): void
    {
        $articulo = Articulo::factory()->create(['stock_actual' => 1]);

        $this->expectException(\InvalidArgumentException::class);

        SalesService::registrarVentaMostrador(
            items: [['articulo_id' => $articulo->id, 'cantidad' => 5]],
            formaPago: 'Efectivo',
        );
    }
}
