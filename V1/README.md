# ERP Librería Bolivia — MVP V1 (Laravel + Filament + Livewire)

Implementación completa del PRD (`PRD/prd.md`): **Sistema Integral de Gestión para Librería** (ERP Comercial, Almacén y Contabilidad) para Bolivia.

## Stack (según PRD)

| Componente | Versión instalada |
|---|---|
| Laravel | 13.x (cumple "Laravel 11+" del PRD) |
| Filament PHP | v3.3 (panel de administración y POS) |
| Livewire | v3 (catálogo público y carrito) |
| Base de datos | PostgreSQL (producción) / SQLite (demo local) |
| PHP | 8.3+ |

## Módulos implementados

### Módulo 1 — Catálogo Público y Venta Web (`/`)
- Exposición minimalista de libros y material de escritorio, categorizada.
- Carrito interactivo en Livewire con persistencia en sesión.
- Checkout simplificado: Nombre/Razón Social, NIT/CI, Email.
- **QR estático** en pantalla con instrucciones de pago + campo `file` para el comprobante (JPG/PNG).
- La venta nace en el panel con estado **"Pendiente de Validación"**.

### Módulo 2 — Panel Admin de Ventas, Caja y Créditos (`/admin`)
- Aprobación/rechazo manual de ventas web con revisión visual del comprobante.
- **POS de mostrador** (búsqueda por texto/ID, sin lectores de barras).
- Ventas **a crédito** + historial de pagos/cuotas por cliente (`Cobrar crédito`).
- **Apertura y cierre de caja diaria** en Bs con arqueo (diferencia vs teórico).

### Módulo 3 — Compras (flujo secuencial inalterable)
`Cotización → Oferta → Orden de Compra (P.C.) → Nota de Ingreso (E.M.) → Factura F.fob → Costos Adicionales/Prorrateo → Gasto de Importación (I.P.) → Pago a Proveedor`
- **Prorrateo**: reparte aranceles/fletes/estiba proporcionalmente al FOB de cada línea y recalcula el costo real unitario.
- Casilla **GND** (Gasto No Deducible): compras sin factura fiscal contabilizan el 100% como GND interno, sin crédito fiscal.

### Módulo 4 — Inventario y Ecuación de Costos
- Ficha técnica: Título/Nombre, Autor, Editorial, Categoría, Stock actual/mínimo, CMP, P.V.
- Ajustes: **Donación** (stock sin tocar CMP), **Compra GND** (costo directo), **Pérdida/Deterioro** (egreso).
- **Automatización crítica**: tras E.M. con prorrateo o ajuste que altere costos → recálculo del **CMP** y del **P.V.** con margen parametrizable (`config/accounting.php`).
- Kardex por artículo (entradas/salidas/saldo).

### Módulo 5 — Contabilidad Automatizada
- **Plan de Cuentas** de Bolivia precargado vía seeder (Caja, Banco, Inventarios, CxC, CxP, Gastos Deducibles/GND, Ingresos por Ventas, Débito/Crédito Fiscal, IT).
- Asientos automáticos al aprobar ventas y registrar compras, con **partida doble estricta** (Debe − Haber = 0) validada antes de persistir.
- **Libro Diario** filtrable por fechas + **Estado de Resultados** dinámico en el panel.
- Todo flujo stock↔contabilidad corre dentro de `DB::transaction` (rollback si el asiento falla).

### Módulo 6 — Simulador de Facturación Electrónica
- Generación simulada de **CUFD / CUIS / CUF / Código de Control / hash de firma digital**.
- **Recibo Informativo de Venta** imprimible con estructura del SIN: datos de empresa, NIT, N° factura, autorización, detalle, subtotal, Total Base Crédito Fiscal, débito fiscal 13% y **leyenda obligatoria de la Ley 453**.

## Arquitectura (buenas prácticas del PRD, Sección 6)

- **SRP**: lógica de negocio aislada en Servicios, no en modelos ni recursos Filament:
  - `app/Services/AccountingService.php` — asientos y partida doble.
  - `app/Services/InventoryService.php` — CMP, P.V. y ajustes.
  - `app/Services/PurchaseService.php` — flujo de compras y prorrateo.
  - `app/Services/SalesService.php` — POS, checkout web, créditos, caja.
  - `app/Services/InvoiceService.php` — facturación simulada.
- **Transacciones de BD**: toda mutación de stock + asiento va envuelta en `DB::transaction`.
- **PHPDoc**: cada función de negocio documentada con sus variables financieras.

## Instalación

```bash
cd V1
composer install
cp .env.example .env          # configurar DB_CONNECTION=pgsql para producción
php artisan key:generate
touch database/database.sqlite # solo para demo local
php artisan migrate --seed
php artisan serve              # catálogo en /  ·  panel en /admin
```

> Nota del entorno de demo: esta máquina no trae la extensión `pdo_sqlite`, por lo que se incluye el binario en `storage/php-extensions/` y el wrapper `./php-dev` (uso: `./php-dev artisan serve`). En producción con PostgreSQL usa el `php` normal.

### Credenciales demo
- Panel: **admin@libreria.bo** / **admin123**

## Pruebas (criterios de aceptación del PRD, Sección 7)

```bash
./php-dev artisan test
```

Cobertura: partida doble exacta en compra GND (`Debe − Haber = 0`), prorrateo de importación con recálculo proporcional de costo unitario/CMP/P.V. sin alterar lotes históricos, y checkout anónimo completo (2 libros → NIT/QR/comprobante JPG → recibo imprimible) sin errores 500.

## Excluido explícitamente (según PRD)
- Conexión real con Web Services del SIN ni firmas ADSIB/Digicert reales.
- Webhooks/APIs bancarias (la validación del QR es visual, con comprobante adjunto).
- Lectores de barras físicos o tiqueteras térmicas.
