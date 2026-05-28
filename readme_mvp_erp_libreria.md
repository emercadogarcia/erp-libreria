# ERP Librería Bolivia — MVP

Sistema Integral de Gestión para Librería enfocado en:
- Ventas
- Inventario
- Compras
- Contabilidad
- Facturación Electrónica Simulada
- Catálogo Web

Este proyecto representa un MVP (Producto Mínimo Viable) diseñado para validar la operación comercial y administrativa de una librería en Bolivia.

---

# Objetivo del Proyecto

El objetivo principal es centralizar los procesos críticos de una librería dentro de una única plataforma:

- Gestión de productos
- Control de stock
- Flujo de compras nacionales e internacionales
- Ventas físicas y online
- Control contable automatizado
- Simulación de facturación electrónica boliviana

El MVP está orientado a:
- Validar procesos de negocio
- Mostrar una demo funcional al cliente
- Servir como base para el desarrollo completo en Laravel + Filament + PostgreSQL

---

# Stack Tecnológico Planeado

## Backend
- Laravel 11+
- PHP 8.3+
- PostgreSQL

## Frontend
- Blade
- Livewire
- Filament PHP v3

## MVP Visual Actual
- HTML
- TailwindCSS
- React (versión prototipo inicial)

---

# Estructura del MVP

## 1. Catálogo Público

Características:
- Visualización de libros y artículos
- Carrito de compras
- Checkout simplificado
- Generación de QR de pago
- Carga manual de comprobante

Objetivo:
Permitir ventas online básicas sin integración bancaria.

---

## 2. Panel Administrativo

Características:
- Validación manual de ventas
- Punto de Venta (POS)
- Gestión de clientes
- Gestión de créditos
- Apertura y cierre de caja

Objetivo:
Centralizar operaciones comerciales internas.

---

## 3. Compras e Importaciones

Flujo:
1. Cotización
2. Oferta
3. Orden de Compra
4. Nota de Ingreso
5. Factura FOB
6. Prorrateo
7. Pago a proveedor

Características:
- Compras nacionales e internacionales
- Costos adicionales
- Gastos no deducibles (GND)
- Recalculo automático de costos

Objetivo:
Controlar correctamente costos reales de inventario.

---

## 4. Inventario

Características:
- Stock actual
- Stock mínimo
- Costo Medio Ponderado (CMP)
- Ajustes manuales
- Donaciones
- Pérdidas
- Compras GND

Objetivo:
Mantener consistencia entre inventario físico y financiero.

---

## 5. Contabilidad Automatizada

Características:
- Libro Diario
- Partida doble automática
- Débito fiscal
- Crédito fiscal
- Estado de resultados

Ecuación contable:

```txt
Debe - Haber = 0
```

Objetivo:
Automatizar registros contables básicos.

---

## 6. Facturación Electrónica Simulada

Características:
- Simulación CUFD/CUIS
- Recibo informativo
- Estructura fiscal boliviana
- Datos fiscales simulados

Importante:
Este MVP NO se conecta todavía al SIN.

---

# Estado Actual del Proyecto

## Incluido en el MVP

- Demo visual funcional
- Diseño UI/UX inicial
- Simulación de procesos
- Flujo de navegación
- Arquitectura conceptual

## No incluido todavía

- Backend real
- Base de datos real
- APIs
- Persistencia de datos
- Autenticación
- Integración SIN
- Integración bancaria

---

# Archivos Importantes

## Versión React

```txt
mvp_libreria_erp_demo.jsx
```

Versión inicial del prototipo usando React.

---

## Versión HTML

```txt
index.html
```

Versión simplificada lista para:
- demos
- presentaciones
- reuniones con cliente
- hosting estático

---

# Cómo Ejecutar la Versión HTML

## Opción rápida

1. Descargar el archivo:

```txt
index.html
```

2. Abrir con doble clic.

3. El navegador mostrará el MVP.

No requiere instalación.

---

# Cómo Ejecutar la Versión React

## Requisitos

- Node.js
- npm

## Instalación

```bash
npm install
```

## Ejecutar

```bash
npm run dev
```

---

# Próximas Etapas del Proyecto

## Fase 1
- Backend Laravel
- PostgreSQL
- Login
- Roles y permisos

## Fase 2
- Inventario real
- Compras reales
- POS funcional

## Fase 3
- Contabilidad automatizada
- Reportes financieros

## Fase 4
- Facturación electrónica SIN
- Integraciones externas

## Fase 5
- E-commerce completo
- Dashboard avanzado
- Reportes BI

---

# Arquitectura Planeada

```txt
Frontend (Blade + Livewire)
        ↓
Laravel Services
        ↓
PostgreSQL
```

Servicios planeados:
- InventoryService
- AccountingService
- PurchaseService
- SalesService
- InvoiceService

---

# Buenas Prácticas Planeadas

- SRP (Single Responsibility Principle)
- Services Layer
- DB Transactions
- PHPDoc
- Arquitectura modular
- Código desacoplado

---

# Objetivo Comercial del MVP

Este MVP está diseñado para:

- Mostrar visión del sistema
- Validar alcance funcional
- Obtener aprobación del cliente
- Reducir riesgo técnico
- Facilitar planificación del desarrollo real

---

# Roadmap General

| Fase | Estado |
|---|---|
| UI MVP | ✅ |
| HTML Demo | ✅ |
| Arquitectura Inicial | ✅ |
| Backend Laravel | Pendiente |
| PostgreSQL | Pendiente |
| Filament Admin | Pendiente |
| Contabilidad Real | Pendiente |
| Facturación SIN | Pendiente |

---

# Autor

Proyecto ERP Librería Bolivia
MVP Comercial y Arquitectura Inicial

---

# Licencia

Uso interno / Demo Comercial / Desarrollo Privado

