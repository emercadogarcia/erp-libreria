# 📄 Documento de Requerimientos del Producto (PRD) - MVP

**Proyecto:** Sistema SaaS de Gestión Integral (Inventario, Ventas, Facturación Electrónica y Contabilidad)  
**Alcance:** Empresa única con operación multi-sucursal.  
**Región:** Bolivia / Sudamérica (Enfoque en normativa fiscal local - SIN / SIAT).  
**Stack Tecnológico:** Backend: Laravel 12 | Frontend/Panel: Filament (v3/v4) + Tailwind CSS  
**Versión:** 1.2 (Optimizado)  
**Fecha:** Junio 2026  

---

## 🏛️ 1. ARQUITECTURA Y REGLAS TRANSVERSALES

* **Stack Tecnológico:** Desarrollo ágil basado en **Laravel 12** para la lógica de negocio, APIs y capa de datos, utilizando **Filament** como framework principal para la construcción de la interfaz de administración (paneles, tablas, formularios y dashboards).
* **Multi-sucursal Estricto:** Todas las entidades y transacciones (inventario, ventas, contabilidad) deben estar vinculadas obligatoriamente a un `sucursal_id` y filtradas globalmente mediante *Global Scopes* de Laravel según los permisos del usuario autenticado.
* **Auditoría y Control de Trazabilidad (Tracking):** * Sistema de autenticación robusto (Laravel Sanctum/Breeze integrado nativamente con Filament).
  * **Estructura de Control de Registros:** Todas las tablas de la base de datos (a excepción de tablas pivote puras) deben incluir obligatoriamente los siguientes campos:
    * `created_at` (timestamp): Fecha y hora de creación del registro.
    * `updated_at` (timestamp): Fecha y hora de la última modificación.
    * `deleted_at` (timestamp, nullable): Para soporte de *Soft Deletes* (eliminación lógica). **Queda prohibido el uso de `DELETE` físico en registros operativos.**
    * `created_by` (foreign key -> `users.id`): Usuario que creó el registro.
    * `updated_by` (foreign key -> `users.id`, nullable): Último usuario que modificó el registro.
  * **Automatización:** Se deben utilizar *Model Observers* o traits personalizados en Laravel para poblar estos campos de forma automática y transparente al framework. Se recomienda el uso del paquete `owen-it/laravel-auditing` para logs inmutables de auditoría en tablas críticas.
* **Precisión Financiera (Manejo de Moneda):** Queda estrictamente prohibido el uso de tipos de datos `float` o `double` para montos, precios o costos. Se utilizará el tipo de dato `decimal(14, 4)` en base de datos para mitigar errores de redondeo, y se formateará a `decimal(14, 2)` únicamente en la capa de presentación y envío de facturas según exige el SIN.
* **Integridad de Contadores:** Los consecutivos (facturas, asientos) se gestionarán mediante **Secuencias Nativas de Base de Datos** (PostgreSQL/MySQL) o transacciones con bloqueo `FOR UPDATE`. *Queda estrictamente prohibido el uso de `MAX(id) + 1` en el código.* Los números anulados **no** se reutilizan.
* **Patrón de Adaptador (Adapter Pattern):** El módulo de facturación usará una interfaz de Laravel para permitir cambiar entre "Conexión Directa al Gobierno (SIAT)" y "Conexión vía PSE (Proveedor de Servicios Electrónicos)" mediante configuración de entorno (`.env`), sin reescribir la lógica central del sistema.

---

## 🎨 2. PRINCIPIOS DE DISEÑO UI/UX (Filament + Tailwind)

* **Minimalismo Funcional:** Aprovechar los componentes nativos de Filament (Forms, Tables, Widgets) pero con una capa de personalización Tailwind CSS para eliminar el "ruido visual". Solo mostrar la información estrictamente necesaria en cada vista.
* **Intuitividad y Flujos Cortos:** Diseñar flujos de trabajo que requieran la menor cantidad de clics posible. Ejemplo: Botones de acción rápida (Timbrar, Anular, Devolver) visibles directamente en la tabla de registros (*Table Actions*).
* **Retroalimentación Visual Inmediata:** Uso extensivo de notificaciones `Toast` (éxito, error, advertencia), estados de carga (*skeleton loaders*) y validaciones asíncronas en tiempo real en los formularios de Filament para guiar al usuario final sin frustración.
* **Adaptabilidad (Responsive):** El panel de Filament debe ser totalmente responsivo, permitiendo que los usuarios de almacén o gerencia revisen dashboards y aprueben pedidos desde tablets o dispositivos móviles.

---

## 📦 3. MÓDULO DE INVENTARIO Y ALMACENES

### 3.1. ABM de Artículos
* **Campos base:** Nombre, descripción, categoría, unidad de medida, tipo (con lote / sin lote), estado, `codigo_sin` (Código de Producto de Impuestos Nacionales para homologación).
* **Extensibilidad:** Campo `atributos_extra` (tipo JSON) para agregar características futuras o propiedades específicas por industria sin alterar el esquema relacional.
* **Codificación:** Generador automático de código único al crear el artículo (ej: `ART-[AÑO]-[SECUENCIAL_5_DIGITOS]`) gestionado desde un Service de Laravel.

### 3.2. Gestión de Lotes (Batch)
* Aplica condicionalmente solo si el artículo está marcado como "Con Lote".
* **Campos obligatorios:** Código de lote (único), nombre, fecha de caducidad, proveedor, UPC/EAN, PMP (Precio Medio Ponderado), cantidad actual, unidad de medida.
* **Campos de control y ubicación:** Fecha de Fabricación, Lote del Fabricante, Ubicación Física (Pasillo-Estante), Estado del Lote (Disponible, Cuarentena, Rechazado), Condiciones de Almacenamiento.

### 3.3. Almacenes y Zonas (Multi-sucursal)
* Cada almacén asignado a una `sucursal_id`.
* **Zonas:** Configuración booleana `es_ubicada`. Si es `true`, exige coordenadas físicas (Pasillo/Estante/Nivel); si es `false`, es una zona general o de tránsito.

### 3.4. Motor de Costeo (PMP Híbrido)
* **Costo Provisional (Diario):** Utiliza el *último PMP calculado* como costo provisional para las salidas del día a día.
* **Recálculo por Compra:** Micro-recálculo automático del PMP al registrar e ingresar una nueva compra para mantener el costo provisional actualizado.
* **Cierre Mensual (Obligatorio):** Comando de Laravel (`php artisan inventory:recalculate-pmp`) programado vía Cron a fin de mes. Recalcula el PMP real y genera automáticamente un asiento de "Ajuste de Costo de Ventas" en el módulo contable.

### 3.5. Movimientos de Inventario (Kardex)
* Registro inmutable (solo operaciones `INSERT`). Tipos: Ingreso por compra, Traslado entre zonas/sucursales, Salida por venta, Ingreso por devolución, Anulación.
* Registra obligatoriamente: `sucursal_origen_id`, `sucursal_destino_id`, `lote_id`, `cantidad`, `costo_unitario_movimiento`, control de auditoría (`created_at`, `created_by`).

### 3.6. API de Ingreso de Mercadería
* Endpoint `POST /api/v1/inventory/ingress` con autenticación vía Laravel Sanctum, diseñado para recibir cargas JSON masivas y procesarlas en segundo plano utilizando *Laravel Jobs/Queues* para no bloquear la interfaz del usuario.

### 3.7. Dashboard Ejecutivo y Reportes (Filament Widgets)
* **Widgets:** Tarjetas de KPIs (Valorización total de inventario, Stock por vencer, Alertas de quiebre de stock).
* **Gráficos:** Integración de Filament Charts (Chart.js) para visualizar Ingresos vs. Salidas y el Top 10 de artículos de baja rotación.
* **Reportes:** Tablas de Filament con filtros avanzados (por fecha, sucursal, artículo) y acción de exportación nativa a CSV/Excel.

---

## 🛒 4. MÓDULO DE VENTAS Y FACTURACIÓN ELECTRÓNICA

### 4.1. Flujo de Pedido y Alistamiento
* **Pedido:** Registro de ítems. Al cambiar al estado "Aceptado", un evento de Laravel desencadena una **Reserva de Stock** (*soft-lock*) en el almacén de la sucursal para evitar el sobre-stock.
* **Alistamiento (Picking):** Vista en Filament que sugiere el lote a despachar aplicando la regla **FEFO** (*First Expired, First Out*). Al confirmar, se descuenta el stock real y se libera la reserva previa.

### 4.2. Motor de Facturación Electrónica (Normativa SIAT Bolivia)
* **Configuración Local:** Panel por sucursal para almacenar credenciales, Token Delegado, Código de Sistema, y gestionar los códigos **CUFD** (Código Único de Facturación Diaria) y **CUF** (Código Único de Facturación).
* **Validación de Emisión:** El botón "Timbrar / Emitir Factura" en Filament se valida tanto en cliente como en servidor. No se puede procesar si el estado contable del documento no es "Contabilizada" (`is_accounted = true`).
* **Soporte de Contingencias:** El sistema debe prever estados de desconexión con el SIN, permitiendo la emisión fuera de línea (en contingencia) firmada localmente y su posterior envío masivo mediante un Job programado de Laravel al restablecerse la conexión.
* **Dashboard de Timbrado:** Tabla con filtros por estado de emisión ante el SIN (badges: verde = Válida, amarillo = Contingencia, rojo = Rechazada/Anulada) con acciones de reintento automatizadas para fallos de red.

### 4.3. Devoluciones (Nota de Crédito y Débito)
* **Origen Obligatorio:** Enlazada estrictamente a una factura original emitida. Permite selección parcial o total de ítems.
* **Lógica Inversa Estricta:** Reingresa el stock al lote de origen (o lote especial de devoluciones), bloquea el precio unitario al valor histórico de la venta, genera el documento fiscal "Nota de Crédito y Débito" y lo envía a timbrar ante el SIAT.

### 4.4. Anulación de Factura
* Proceso inverso total bajo normativa SIN (dentro de los plazos legales permitidos en Bolivia): Reingreso automático de stock + Generación de Asiento Contable de Reversión + Solicitud de anulación enviando el motivo correspondiente a la API del SIN.

### 4.5. Contadores de Facturación
* Gestión mediante una tabla dedicada `document_counters` con bloqueo de base de datos a nivel de fila (`LOCK IN SHARE MODE` o `FOR UPDATE`), segmentada por `sucursal_id`, `punto_de_venta` y `tipo_documento`.

---

## 📒 5. MÓDULO DE CONTABILIZACIÓN

### 5.1. Regla de Negocio Crítica
* Bloqueo estricto a nivel de código (Laravel Policy / Middleware): No se permite la ejecución del método de facturación/timbrado si el modelo de la factura no ha pasado exitosamente por la capa de generación de asientos (`is_accounted = true`).

### 5.2. Generación de Asientos Automáticos
* **Venta:** Débito: Clientes/Caja/Banco | Crédito: Ingresos por Ventas | Crédito: Débito Fiscal IVA (13%). *Nota: Controlar internamente el Impuesto a las Transacciones (IT 3%) como asiento compuesto simultáneo.*
* **Nota de Crédito y Débito:** Asiento inverso proporcional calculando el Crédito/Débito IVA según corresponda.
* **Anulación:** Asiento de reversión total (mismos montos en lados opuestos) para dejar el saldo operativo en cero y mantener la correlatividad e integridad del Libro Diario.
* **Ajuste de PMP:** Asiento automático generado por el comando de cierre mensual de inventarios.

### 5.3. Gestión de Asientos
* **Contador de Asientos:** Secuencia nativa de BD para asegurar la correlatividad cronológica del Libro Diario.
* **Anulación de Asientos:** *Queda estrictamente prohibido el uso de DELETE físico.* Se aplica "Anulación Lógica" mediante cambio de estado a "ANULADO", resguardando los datos originales, registrando `updated_at`/`updated_by`, y generando un asiento espejo de reversión con la fecha actual.

---

## 🛠️ 6. REQUISITOS NO FUNCIONALES Y DE SEGURIDAD

1. **Manejo de Concurrencia:** Transacciones de base de datos (`DB::transaction()`) obligatorias en todos los procesos críticos: confirmación de ventas, devoluciones, anulaciones y actualización de contadores.
2. **Seguridad, Roles y Permisos:** Implementación basada en políticas nativas de Laravel (*Gates/Policies*) integradas con un sistema de Roles y Permisos (ej: `spatie/laravel-permission` optimizado para Filament). Restricciones granulares (ej: solo rol "Contador Senior" anula asientos; solo "Operador Almacén" confirma picking).
3. **Rendimiento e Indexación:** Indexación obligatoria en la base de datos para llaves foráneas y campos de consulta frecuente: `sucursal_id`, `articulo_id`, `user_id`, `fecha`, junto con índices compuestos para reportabilidad. Uso de Redis como driver de caché para consultas pesadas del dashboard.
4. **Validaciones Unificadas:** Toda regla de negocio debe validarse en la capa del Backend (*Form Requests* o *Service Classes*) y no depender únicamente de las validaciones de los componentes de formularios de Filament, asegurando la integridad si las acciones se ejecutan vía API.

---

## 🚀 7. PRÓXIMOS PASOS SUGERIDOS (Hoja de Ruta de Desarrollo)

1. **Fase 1: Configuración Base, Arquitectura y Datos (Semana 1)**
   * Instalación de Laravel 12 + Filament.
   * Implementación del sistema de roles, permisos y automatización de campos de auditoría (`created_at`, `updated_at`, `created_by`, `updated_by`).
   * Creación de migraciones base con soporte de *Soft Deletes* (`sucursales`, `articulos`, `lotes`, `kardex`, `contadores`).
2. **Fase 2: Núcleo de Inventario y Costeo (Semanas 2-3)**
   * Desarrollo de recursos Filament (CRUDs) para Artículos (con homologación SIN), Lotes y Almacenes.
   * Implementación del Service de costeo PMP y el comando programado de cierre mensual.
3. **Fase 3: Ventas y Contabilización (Semanas 4-5)**
   * Desarrollo del flujo: Pedido (soft-lock) -> Alistamiento (FEFO) -> Descuento de Stock.
   * Arquitectura de la lógica de generación de asientos contables automáticos (Venta, IT, Reversiones) y su validación estricta.
4. **Fase 4: Facturación Electrónica SIAT (Semanas 6-7)**
   * Desarrollo del Adapter Pattern para la comunicación con la API SIAT de Impuestos Nacionales o PSE.
   * Configuración de *Laravel Queues/Jobs* para procesamiento asíncrono de timbrado y control de contingencias fuera de línea.
5. **Fase 5: Dashboards, UX Refinement y QA (Semana 8)**
   * Construcción de Widgets interactivos en Filament para KPIs gerenciales y contables.
   * Pruebas de estrés y concurrencia en asignación de contadores y bloqueos de stock.
   * Auditoría final de usabilidad, alertas Toast y adaptabilidad móvil.

---
*Documento de Requisitos del Producto (PRD) optimizado para el desarrollo del MVP. Sujeto a revisiones iterativas bajo metodologías ágiles.*