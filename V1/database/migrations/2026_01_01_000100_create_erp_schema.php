<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración principal del MVP: Sistema Integral de Gestión para Librería.
 * Crea el esquema de datos esencial definido en el PRD (Sección 5) ampliado
 * con las tablas operativas de ventas, caja, créditos, ajustes y facturación.
 *
 * Nota de precisión financiera: todos los montos usan DECIMAL(14,4) para
 * mitigar errores de redondeo (el PRD estándar exige decimal(14,4)); el formato
 * final decimal(14,2) solo se aplica en la capa de presentación/facturas.
 */
return new class extends Migration
{
    /**
     * Ejecuta la creación del esquema completo del ERP.
     */
    public function up(): void
    {
        // ------------------------------------------------------------------
        // Módulo 4: Catálogo (categorías primero para respetar la FK de artículos)
        // ------------------------------------------------------------------
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Módulo 4: Catálogo de artículos (libros y material de escritorio)
        // ------------------------------------------------------------------
        Schema::create('articulos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_sistema', 50)->unique();
            $table->string('titulo_nombre', 255);
            $table->string('autor', 150)->nullable();
            $table->string('editorial', 150)->nullable();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->enum('tipo', ['libro', 'escritorio'])->default('libro');
            $table->integer('stock_actual')->default(0);
            $table->integer('stock_minimo')->default(5);
            $table->decimal('costo_medio_ponderado', 14, 4)->default(0);
            $table->decimal('precio_venta', 14, 4)->default(0);
            $table->boolean('es_gnd')->default(false); // Gasto No Deducible (sin factura fiscal)
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['titulo_nombre', 'autor', 'editorial']);
        });

        // ------------------------------------------------------------------
        // Módulo 2: Clientes y control de créditos
        // ------------------------------------------------------------------
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_razon_social', 255);
            $table->string('nit_ci', 20); // NIT o CI exigido por el SIN
            $table->string('email', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->decimal('limite_credito', 14, 4)->default(0);
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Módulo 3: Proveedores y flujo secuencial de compras
        // ------------------------------------------------------------------
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_razon_social', 255);
            $table->string('nit', 20)->nullable();
            $table->string('pais', 80)->default('Bolivia'); // Nacional o Internacional
            $table->string('contacto', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('compras_cabecera', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique(); // COMP-2026-00001
            $table->enum('tipo_compra', ['Nacional', 'Internacional']);
            // Flujo secuencial inalterable del PRD:
            // Cotizacion -> Oferta -> OrdenCompra -> NotaIngreso -> Facturado -> GastoImportacion -> Pagado
            $table->string('estado_flujo', 30)->default('Cotizacion');
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->date('fecha_emision');
            $table->date('fecha_estimada_recepcion')->nullable();
            $table->decimal('valor_fob', 14, 4)->default(0);           // Valor FOB de la factura de proveedor
            $table->decimal('costos_adicionales_prorrateo', 14, 4)->default(0); // Aranceles + fletes + estiba
            $table->boolean('es_gnd')->default(false);                 // Gasto No Deducible
            $table->string('glosa', 255)->nullable();
            $table->timestamps();
            $table->index(['estado_flujo', 'tipo_compra']);
        });

        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_cabecera_id')->constrained('compras_cabecera')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos');
            $table->integer('cantidad');
            $table->decimal('costo_unitario_fob', 14, 4)->default(0);   // Costo unitario FOB de línea
            $table->decimal('costo_unitario_final', 14, 4)->default(0); // Costo real tras prorrateo (recalculado)
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Módulo 1 + 2: Ventas web y de mostrador (POS)
        // ------------------------------------------------------------------
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recibo', 30)->unique(); // REC-000001 / FAC-000001
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre', 255)->nullable(); // Datos del checkout web anónimo
            $table->string('cliente_nit_ci', 20)->nullable();
            $table->string('cliente_email', 255)->nullable();
            $table->enum('origen', ['web', 'mostrador'])->default('mostrador');
            $table->enum('estado', ['PendienteValidacion', 'Aprobada', 'Rechazada', 'Anulada'])->default('PendienteValidacion');
            $table->enum('forma_pago', ['QR', 'Efectivo', 'Credito'])->default('QR');
            $table->string('comprobante_path', 255)->nullable(); // Captura del comprobante (web)
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('descuento', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->boolean('es_credito')->default(false);
            $table->timestamp('aprobada_at')->nullable();
            $table->foreignId('aprobada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['estado', 'origen']);
        });

        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 14, 4)->default(0);
            $table->decimal('costo_unitario_historico', 14, 4)->default(0); // CMP al momento de la venta
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Módulo 2: Créditos y cobranzas (historial de pagos/cuotas por cliente)
        // ------------------------------------------------------------------
        Schema::create('pagos_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->decimal('monto', 14, 4)->default(0);
            $table->string('metodo', 50)->default('Efectivo');
            $table->text('observacion')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Módulo 2: Apertura y cierre de caja diario (en Bs.)
        // ------------------------------------------------------------------
        Schema::create('caja_diarias', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->foreignId('user_id')->constrained('users'); // Responsable de caja
            $table->decimal('monto_apertura', 14, 4)->default(0);
            $table->decimal('monto_cierre', 14, 4)->nullable();
            $table->decimal('ventas_efectivo', 14, 4)->default(0);
            $table->decimal('ventas_qr', 14, 4)->default(0);
            $table->decimal('diferencia', 14, 4)->nullable(); // Arqueo: cierre vs. teórico
            $table->enum('estado', ['Abierta', 'Cerrada'])->default('Abierta');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Módulo 4: Ajustes de inventario (donaciones, GND, pérdidas)
        // ------------------------------------------------------------------
        Schema::create('ajustes_inventario', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique(); // AJU-000001
            $table->enum('tipo', ['Donacion', 'CompraGND', 'PerdidaDeterioro']);
            $table->foreignId('articulo_id')->constrained('articulos');
            $table->integer('cantidad'); // Positivo = ingreso, negativo = egreso
            $table->decimal('costo_unitario', 14, 4)->default(0); // Asignado directo (GND) o 0 (donación)
            $table->boolean('afecta_cmp')->default(false); // Donación: no altera CMP
            $table->string('motivo', 255)->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Módulo 5: Contabilidad - Plan de Cuentas, Libro Diario
        // ------------------------------------------------------------------
        Schema::create('plan_cuentas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique(); // 1.1.1.01 Caja Chica, etc.
            $table->string('nombre', 150);
            $table->enum('tipo', ['Activo', 'Pasivo', 'Patrimonio', 'Ingreso', 'Gasto']);
            $table->enum('naturaleza', ['Deudora', 'Acreedora']);
            $table->boolean('es_imputable')->default(true); // false para rubros agrupadores
            $table->timestamps();
        });

        Schema::create('asientos_diario', function (Blueprint $table) {
            $table->id();
            $table->string('numero_asiento', 30)->unique(); // Correlativo AJ-000001
            $table->text('glosa');
            $table->timestamp('fecha_asiento');
            $table->decimal('total_debe', 14, 4)->default(0);
            $table->decimal('total_haber', 14, 4)->default(0);
            $table->string('origen', 50)->nullable(); // venta|compra|ajuste|manual
            $table->foreignId('referencia_id')->nullable(); // id del documento origen
            $table->timestamps();
            $table->index(['fecha_asiento', 'origen']);
        });

        Schema::create('asiento_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asiento_diario_id')->constrained('asientos_diario')->cascadeOnDelete();
            $table->string('cuenta_codigo', 30); // Código del plan de cuentas
            $table->decimal('debe', 14, 4)->default(0);
            $table->decimal('haber', 14, 4)->default(0);
            $table->timestamps();
            $table->index('cuenta_codigo');
        });

        // ------------------------------------------------------------------
        // Módulo 6: Simulador de facturación electrónica (campos SIN)
        // ------------------------------------------------------------------
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->string('numero_factura', 30)->unique();
            $table->string('cuis', 20);        // Código Único de Inscripción de Sucursal (simulado)
            $table->string('cufd', 80);        // Código Único de Facturación Diaria (simulado)
            $table->string('cuf', 100);        // Código Único de Factura (simulado)
            $table->string('codigo_control', 80); // Código de control teórico
            $table->string('firma_digital_hash', 128); // Hash simulado de la firma digital
            $table->date('fecha_emision');
            $table->decimal('total_base_credito_fiscal', 14, 4)->default(0);
            $table->decimal('debito_fiscal', 14, 4)->default(0); // IVA 13% simulado
            $table->string('leyenda', 500); // Leyenda obligatoria de la Ley 453
            $table->timestamps();
        });
    }

    /**
     * Revierte la creación del esquema en orden inverso.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas');
        Schema::dropIfExists('asiento_detalles');
        Schema::dropIfExists('asientos_diario');
        Schema::dropIfExists('plan_cuentas');
        Schema::dropIfExists('ajustes_inventario');
        Schema::dropIfExists('caja_diarias');
        Schema::dropIfExists('pagos_cliente');
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('compra_detalles');
        Schema::dropIfExists('compras_cabecera');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('articulos');
        Schema::dropIfExists('categorias');
    }
};
