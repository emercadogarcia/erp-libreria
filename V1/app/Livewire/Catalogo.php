<?php

namespace App\Livewire;

use App\Models\Articulo;
use App\Services\SalesService;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Catálogo público + carrito + checkout (PRD Módulo 1 - E-commerce Express).
 *
 * Flujo: exposición minimalista categorizada de libros y material de
 * escritorio -> carrito interactivo -> checkout simplificado (Nombre/Razón
 * Social, NIT/CI, Email) -> QR estático de pago + carga del comprobante
 * (JPG/PNG) -> registro "Pendiente de Validación" en el panel Filament.
 */
#[Layout('components.layouts.catalogo')]
#[Title('Librería El Ateneo - Catálogo')]
class Catalogo extends Component
{
    use WithFileUploads;

    /** Filtro de categoría activo (null = todas). */
    public ?int $categoriaId = null;

    /** Búsqueda por texto estructurado (Título, Autor, Editorial). */
    public string $busqueda = '';

    /** Carrito: [articulo_id => cantidad]. */
    public array $carrito = [];

    /** Control del modal de checkout. */
    public bool $checkoutAbierto = false;

    /** Paso actual: 1 datos -> 2 QR + comprobante -> 3 confirmado. */
    public int $paso = 1;

    // Datos del cliente (checkout simplificado)
    public string $nombre = '';

    public string $nit_ci = '';

    public string $email = '';

    /** Captura del comprobante de transferencia (JPG/PNG). */
    public $comprobante;

    /** Venta creada al confirmar (para el recibo preliminar imprimible). */
    public ?int $ventaId = null;

    /**
     * Se ejecuta al iniciar el componente.
     */
    public function mount(): void
    {
        $this->carrito = Session::get('carrito', []);
    }

    /**
     * Artículos filtrados por categoría y búsqueda de texto estructurado.
     */
    public function getArticulosProperty()
    {
        return Articulo::query()
            ->where('activo', true)
            ->where('stock_actual', '>', 0)
            ->when($this->categoriaId, fn ($q) => $q->where('categoria_id', $this->categoriaId))
            ->when($this->busqueda, function ($q) {
                $termino = '%'.$this->busqueda.'%';

                $q->where(fn ($q) => $q
                    ->where('titulo_nombre', 'like', $termino)
                    ->orWhere('autor', 'like', $termino)
                    ->orWhere('editorial', 'like', $termino));
            })
            ->orderBy('titulo_nombre')
            ->get();
    }

    /**
     * Categorías para el menú minimalista.
     */
    public function getCategoriasProperty()
    {
        return \App\Models\Categoria::withCount(['articulos' => fn ($q) => $q->where('activo', true)])->get();
    }

    /**
     * Detalle de los ítems del carrito con subtotales.
     */
    public function getItemsCarritoProperty()
    {
        if (empty($this->carrito)) {
            return collect();
        }

        return Articulo::whereIn('id', array_keys($this->carrito))
            ->get()
            ->map(function (Articulo $articulo) {
                $cantidad = min($this->carrito[$articulo->id], $articulo->stock_actual);

                return [
                    'id' => $articulo->id,
                    'titulo' => $articulo->titulo_nombre,
                    'autor' => $articulo->autor,
                    'precio' => (float) $articulo->precio_venta,
                    'cantidad' => $cantidad,
                    'subtotal' => round((float) $articulo->precio_venta * $cantidad, 2),
                ];
            })
            ->filter(fn ($item) => $item['cantidad'] > 0);
    }

    /**
     * Total del carrito en Bs.
     */
    public function getTotalCarritoProperty(): float
    {
        return round($this->itemsCarrito->sum('subtotal'), 2);
    }

    /**
     * Cantidad de ítems en el carrito.
     */
    public function getConteoCarritoProperty(): int
    {
        return array_sum($this->carrito);
    }

    /**
     * Instrucciones de pago del QR estático.
     */
    public function getInstruccionesPagoProperty(): array
    {
        return [
            'banco' => config('accounting.empresa.cuenta_bancaria'),
            'telefono' => config('accounting.empresa.telefono_qr'),
            'total' => $this->totalCarrito,
        ];
    }

    /**
     * Añade un artículo al carrito (respetando el stock disponible).
     */
    public function agregar(int $articuloId): void
    {
        $articulo = Articulo::findOrFail($articuloId);
        $actual = $this->carrito[$articuloId] ?? 0;

        if ($actual + 1 > $articulo->stock_actual) {
            $this->dispatch('toast', mensaje: "Stock insuficiente de «{$articulo->titulo_nombre}»");

            return;
        }

        $this->carrito[$articuloId] = $actual + 1;
        $this->guardarCarrito();
    }

    /**
     * Quita un artículo del carrito.
     */
    public function quitar(int $articuloId): void
    {
        unset($this->carrito[$articuloId]);
        $this->guardarCarrito();
    }

    /**
     * Cambia la cantidad de un ítem.
     */
    public function cambiarCantidad(int $articuloId, int $cantidad): void
    {
        if ($cantidad <= 0) {
            $this->quitar($articuloId);

            return;
        }

        $articulo = Articulo::findOrFail($articuloId);
        $this->carrito[$articuloId] = min($cantidad, $articulo->stock_actual);
        $this->guardarCarrito();
    }

    /**
     * Abre el modal de checkout (paso 1: datos del cliente).
     */
    public function abrirCheckout(): void
    {
        if (empty($this->carrito)) {
            return;
        }

        $this->checkoutAbierto = true;
        $this->paso = 1;
    }

    /**
     * Avanza al paso 2: QR estático + carga de comprobante.
     */
    public function continuarPago(): void
    {
        $this->validate([
            'nombre' => 'required|min:3|max:255',
            'nit_ci' => 'required|max:20',
            'email' => 'required|email|max:255',
        ]);

        $this->paso = 2;
    }

    /**
     * Confirma el pedido: valida el comprobante y registra la venta web en
     * estado PendienteValidacion (PRD Módulo 1: impacto inmediato en Filament).
     */
    public function confirmarPedido(): void
    {
        $this->validate([
            'comprobante' => 'required|file|mimes:jpg,jpeg,png|max:4096',
        ]);

        $items = collect($this->itemsCarrito)
            ->map(fn ($item) => ['articulo_id' => $item['id'], 'cantidad' => $item['cantidad']])
            ->all();

        $venta = SalesService::registrarVentaWeb($items, [
            'nombre' => $this->nombre,
            'nit_ci' => $this->nit_ci,
            'email' => $this->email,
        ], $this->comprobante);

        $this->ventaId = $venta->id;
        $this->paso = 3;

        Session::forget('carrito');
        $this->carrito = [];
        $this->checkoutAbierto = false;
    }

    /**
     * Persistencia del carrito en sesión.
     */
    protected function guardarCarrito(): void
    {
        Session::put('carrito', $this->carrito);
    }

    /**
     * Render del catálogo.
     */
    public function render()
    {
        return view('livewire.catalogo');
    }
}
