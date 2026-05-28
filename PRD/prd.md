# DOCUMENTO DE REQUERIMIENTOS DE PRODUCTO (PRD) - MVP

## 1. CONTROL DE VERSIONES Y METADATOS
*   **Proyecto:** Sistema Integral de Gestión para Librería (ERP Comercial, Almacén y Contabilidad).
*   **Fase:** Producto Mínimo Viable (MVP - Muestra Funcional).
*   **País de Aplicación:** Bolivia.
*   **Stack Tecnológico:** Laravel 11+ (Backend y Frontend unificados vía Blade/Livewire), Filament PHP v3 (Panel de Administración y POS), PostgreSQL.
*   **Estado:** Aprobado para Desarrollo.

---

## 2. OBJETIVO DEL MVP
El objetivo primordial de este MVP es validar de manera ágil y funcional el núcleo transaccional y operativo de la librería, unificando los flujos de control de inventario (libros y escritorio), compras, ventas, preconfiguración contable y la lógica de facturación electrónica. El sistema operará de forma centralizada sin delegar la lógica a frameworks SPA externos (como React o Vue separados), garantizando un mantenimiento simple, una estructura de costos limpia y un despliegue veloz.

---

## 3. ALCANCE Y LÍMITES DEL MVP (CONTRATO TÉCNICO)

### Qué INCLUYE el MVP:
1.  **Catálogo Web Público:** Interfaz minimalista integrada en el mismo proyecto Laravel (mediante Blade y componentes Livewire) con carrito de compras y generación de código QR estándar de cuenta para confirmación de pago manual (subida de comprobante).
2.  **Panel de Administración Unificado con Filament:** Panel de gestión interna para Inventarios, Ajustes, Clientes, Proveedores, Compras y Ventas.
3.  **Módulo Contable Automático Base:** Creación automática de un asiento de diario en el Libro Diario al consolidar ventas o compras (Principio de Partida Doble), basado en un Plan de Cuentas Estándar parametrizado desde cero para el rubro en Bolivia.
4.  **Simulador de Facturación Electrónica en Línea:** Estructuración y validación interna en la base de datos de los campos obligatorios exigidos por el SIN (campos para CUFD, CUIS, Código de Control, firma digital teórica). Emisión de un "Recibo Informativo de Venta" simulando el formato legal (SLA/Ley de Impuestos de Bolivia).

### Qué NO INCLUYE el MVP (Excluido explícitamente):
1.  Conexión en tiempo real con los Web Services del Servicio de Impuestos Nacionales (SIN) ni firmas digitales tokenizadas reales de la ADSIB/Digicert.
2.  Integración automatizada o webhooks con APIs bancarias (la validación del pago por QR se realiza de forma visual humana adjuntando la captura del comprobante).
3.  Conectividad con hardware periférico específico (lectoras de barras físicas o calibración de tiqueteras térmicas). Las búsquedas de ítems serán directas por texto estructurado (Autor, Editorial, Título).

---

## 4. ESPECIFICACIÓN DETALLADA DE MÓDULOS

### Módulo 1: Catálogo Público y Proceso de Venta Web (E-commerce Express)
*   **Funcionalidad:**
    *   Exposición de productos (Libros y Material de Escritorio) categorizados de manera minimalista.
    *   Carrito de compras interactivo desarrollado con Laravel Livewire.
    *   Checkout simplificado: El cliente ingresa sus datos (Nombre/Razón Social, NIT/CI, Correo Electrónico).
    *   Generación en pantalla de un código QR estático con instrucciones de pago y un campo tipo file para que el usuario adjunte la fotografía de su comprobante de transferencia bancaria.
*   **Impacto Inmediato:** Crea un registro en el panel de Filament con estado "Pendiente de Validación".

### Módulo 2: Panel Administrativo de Ventas, Caja y Créditos (Filament)
*   **Funcionalidad:**
    *   Aprobación manual de ventas web por el administrador tras la revisión visual del comprobante cargado.
    *   Punto de Venta (POS) interno simplificado en Filament para ventas directas en mostrador (búsqueda por texto rápido o ID del ítem, **NO** lectores de barras).
    *   Control de créditos y cobranzas básicos: Registro de ventas con estado "A Crédito" y tabla de historial de pagos/cuotas por cliente.
    *   Apertura y Cierre de Caja diario registrando montos en Bs.

### Módulo 3: Proceso de Compra (Nacional e Internacional)
*   **Funcionalidad:**
    *   Flujo secuencial e inalterable reflejado en pestañas o estados dentro de Filament:
        1.  `Cotización`
        2.  `Oferta`
        3.  `Orden de Compra (P.C.)`
        4.  `Recepción de Mercadería / Nota de Ingreso (E.M.)`
        5.  `Factura de Proveedor (F.fob)` -> Registro del valor FOB de importación o compra local.
        6.  `Costos Adicionales / Prorrateo` -> Formulario para añadir aranceles, fletes y transportes.
        7.  `Registro del Gasto de Importación (I.P.)`
        8.  `Pago a Proveedor`
    *   **Lógica de Prorrateo:** El sistema debe sumar los costos adicionales al valor de la factura del proveedor (`F.fob`) para recalcular de manera automática el costo real unitario ingresado a inventario.
    *   **Gastos No Deducibles (GND):** Casilla de verificación para marcar compras sin factura fiscal que afecten el inventario físico, clasificándolas contablemente como GND internos.

### Módulo 4: Inventario y Ecuación de Costos
*   **Funcionalidad:**
    *   Ficha técnica simplificada del artículo (Título/Nombre, Autor, Editorial, Categoría, Stock actual, Stock mínimo, Costo Unitario, Precio de Venta).
    *   Módulo de Ajustes de Inventario:
        *   `Ingreso por Donación` (Afecta stock sin modificar costos promedio).
        *   `Ingreso por Compra GND` (Afecta stock, costo asignado directo).
        *   `Egreso / Ajuste por Pérdida o Deterioro`.
    *   **Automatización Crítica:** Cada vez que finaliza una Nota de Ingreso (`E.M.`) con prorrateo o se guarda un ajuste que altere los costos de adquisición, el sistema ejecutará en segundo plano el recálculo automatizado del Precio de Venta (P.V.) basado en un margen de ganancia parametrizable y la actualización de la Ecuación del Costo Medio Ponderado (CMP).

### Módulo 5: Contabilidad Automatizada e Informes Financieros
*   **Funcionalidad:**
    *   Plan de Cuentas base pre-cargado vía *Seeder* de Laravel (Cuentas esenciales: Caja Chica, Banco, Inventarios, Cuentas por Cobrar, Cuentas por Pagar, Gastos Deducibles, Gastos No Deducibles, Ingresos por Ventas, Débito Fiscal, Crédito Fiscal).
    *   **Automatización de Asiento Contable:**
        *   Al consolidarse una venta aprobada: Se debita `Caja/Banco` (o `Cuentas por Cobrar`), se debita `Costo de Ventas`; se acredita `Ventas`, se acredita `Débito Fiscal` (13% simulado) y se acredita `Inventarios`.
        *   Al consolidarse una compra: Se ejecuta la partida doble correspondiente segregando el `Crédito Fiscal` (13%) si aplica o asignando al gasto GND según corresponda.
    *   **Informes Disponibles:** Reporte interactivo en Filament del Libro Diario filtrado por fechas y un Estado de Resultados (ER) dinámico preliminar.

### Módulo 6: Simulador de Facturación Electrónica en Línea
*   **Funcionalidad:**
    *   Procesamiento simulado de datos exigidos por el SIN. Al facturar, el backend generará una cadena de texto simulando el hash de la Firma Digital y el código único CUFD/CUIS.
    *   Generación de vista de impresión en formato carta/tique del "Recibo Informativo de Venta" que cumpla con la estructura visual de Impuestos Nacionales de Bolivia (Datos de la empresa, Nit, Nro. Factura/Recibo, Código de Autorización, Detalle del producto, Subtotal, Descuentos, Total Base Crédito Fiscal, Leyenda obligatoria de la ley de impuestos).

---

## 5. MODELADO DE DATOS ESENCIAL (DISEÑO DE BASE DE DATOS)

Para garantizar consistencia absoluta y auditorías limpias, se define la estructura de tablas relacionales clave en PostgreSQL:

```sql
-- Tabla de Artículos de la Librería
CREATE TABLE articulos (
    id SERIAL PRIMARY KEY,
    codigo_sistema VARCHAR(50) UNIQUE,
    titulo_nombre VARCHAR(255) NOT NULL,
    autor VARCHAR(150) NULL,
    editorial VARCHAR(150) NULL,
    stock_actual INT DEFAULT 0,
    stock_minimo INT DEFAULT 5,
    costo_medio_ponderado NUMERIC(12, 2) DEFAULT 0.00,
    precio_venta NUMERIC(12, 2) DEFAULT 0.00,
    creado_el TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Registro Secuencial Compra Internacional / Nacional
CREATE TABLE compras_cabecera (
    id SERIAL PRIMARY KEY,
    tipo_compra VARCHAR(20), -- 'Nacional' o 'Internacional'
    estado_flujo VARCHAR(30), -- 'Cotizacion','Oferta','OrdenCompra','NotaIngreso','Facturado','Pagado'
    proveedor_id INT,
    valor_fob NUMERIC(12, 2) DEFAULT 0.00,
    costos_adicionales_prorrateo NUMERIC(12, 2) DEFAULT 0.00,
    es_gnd BOOLEAN DEFAULT FALSE, -- Gasto No Deducible
    fecha_emision DATE NOT NULL
);

-- Transacciones Contables (Libro Diario)
CREATE TABLE asientos_diario (
    id SERIAL PRIMARY KEY,
    glosa TEXT NOT NULL,
    fecha_asiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE asiento_detalles (
    id SERIAL PRIMARY KEY,
    asiento_diario_id INT REFERENCES asientos_diario(id) ON DELETE CASCADE,
    cuenta_codigo VARCHAR(30) NOT NULL,
    debe NUMERIC(12, 2) DEFAULT 0.00,
    haber NUMERIC(12, 2) DEFAULT 0.00
);

## 6. ARQUITECTURA DE CÓDIGO Y BUENAS PRÁCTICAS DE DESARROLLO

Para asegurar la robustez requerida por el PRD y facilitar el mantenimiento futuro por cualquier equipo de ingeniería, se dictaminan las siguientes pautas de codificación obligatorias:

* **Principio de Responsabilidad Única (SRP):** Las operaciones complejas (como el cálculo del Costo Medio Ponderado tras un prorrateo de importación o el registro de asientos contables automáticos) no deben escribirse dentro de las clases de recursos de Filament ni en los modelos directamente. Deben encapsularse de forma aislada en clases de Servicio independientes (ej. `app/Services/AccountingService.php`, `app/Services/InventoryService.php`).
* **Transacciones de Base de Datos (Seguridad Financiera):** Todo flujo que involucre afectar de manera simultánea el stock físico y el libro contable debe envolverse estrictamente dentro de transacciones transaccionales de la base de datos (`DB::transaction`). Si el registro contable o el guardado del asiento falla, la modificación del inventario debe revertirse por completo (*rollback*) para evitar descuadres.
* **Comentarios de Código Obligatorios:** Cada función personalizada de negocio, cálculo de prorrateo o mutación contable debe estar estrictamente documentada siguiendo el estándar PHPDoc, describiendo las variables financieras procesadas para facilitar el mantenimiento preventivo.

### Ejemplo de Implementación Requerido en PHP:

```php
<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\AsientoDiario;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Clase de Servicio para la automatización de procesos contables del ERP.
 * Balancea y genera la partida doble requerida por la normativa boliviana.
 */
class AccountingService
{
    /**
     * Registra un asiento contable automático a partir de una venta consolidada.
     * * @param Venta $venta Modelo de la venta aprobada en el sistema.
     * @return AsientoDiario
     * @throws Exception Si el asiento no cumple con el principio de partida doble.
     */
    public function generarAsientoVenta(Venta $venta): AsientoDiario
    {
        // Se ejecuta bajo una transacción segura de base de datos
        return DB::transaction(function () use ($venta) {
            
            // 1. Crear la cabecera del Asiento de Diario
            $asiento = AsientoDiario::create([
                'fecha' => now(),
                'glosa' => "Asiento automático - Venta según Recibo Nro. {$venta->numero_recibo}",
                'total_debe' => $venta->total,
                'total_haber' => $venta->total,
            ]);

            // 2. Registro del Debe: Ingreso a Caja o Banco (100% del efectivo recibido)
            $asiento->detalles()->create([
                'plan_cuenta_id' => config('accounting.cuentas.caja_general'),
                'debe' => $venta->total,
                'haber' => 0.00
            ]);

            // 3. Registro del Haber: Simulación del desglose fiscal boliviano (87% Ingreso Neto)
            $ingresoNeto = $venta->total * 0.87;
            $asiento->detalles()->create([
                'plan_cuenta_id' => config('accounting.cuentas.ingreso_ventas'),
                'debe' => 0.00,
                'haber' => $ingresoNeto
            ]);

            // 4. Registro del Haber: Simulación del Débito Fiscal IVA (13% reservado para el SIN)
            $debitoFiscal = $venta->total * 0.13;
            $asiento->detalles()->create([
                'plan_cuenta_id' => config('accounting.cuentas.debito_fiscal'),
                'debe' => 0.00,
                'haber' => $debitoFiscal
            ]);

            // 5. Validación técnica de seguridad (Partida Doble Estricta)
            $sumDebe = $asiento->detalles()->sum('debe');
            $sumHaber = $asiento->detalles()->sum('haber');

            if (abs($sumDebe - $sumHaber) > 0.0001) {
                throw new Exception("Error Crítico: El asiento contable no cuadra. Transacción abortada.");
            }

            return $asiento;
        });
    }
}
```

---

## 7. CRITERIOS DE ACEPTACIÓN Y VALIDACIÓN TÉCNICA (QA)

El MVP se considerará formalmente aprobado por control de calidad y listo para ser expuesto como muestra funcional cuando supere satisfactoriamente los siguientes escenarios de prueba:

* **Prueba de Consistencia Contable por Partida Doble:** Al simular e ingresar una compra local de material de escritorio marcada como Gasto No Deducible (GND), el sistema debe reflejar automáticamente el asiento en el Libro Diario asignando el 100% del valor a la cuenta de costo/gasto interno sin desglosar crédito fiscal. El balance matemático final del asiento debe ser exacto, cumpliendo la ecuación de equilibrio general:
  
  $$Debe - Haber = 0$$

* **Prueba de Prorrateo de Importaciones:** Al procesar una orden de compra internacional con un valor de adquisición base, e ingresar posteriormente costos logísticos adicionales (aranceles aduaneros, fletes locales o estiba), el sistema debe recalcular automáticamente el costo unitario de inventario de los libros. El costo final debe incrementarse de manera proporcional y transparente en la base de datos sin alterar los registros históricos de lotes pasados.

* **Prueba de Checkout e Interfaz de Usuario Externa:** Un usuario anónimo navegando en el catálogo público debe poder completar el flujo de compra de manera fluida (añadir dos libros al carrito, ingresar NIT/Razón Social, visualizar el código QR estático de pago y subir un archivo en formato JPG o PNG como captura de pantalla de su transferencia bancaria) sin que el servidor lance excepciones o errores HTTP 500, desplegando finalmente un recibo preliminar imprimible con el formato visual requerido.