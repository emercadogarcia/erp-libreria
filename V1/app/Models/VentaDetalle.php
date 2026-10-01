<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una venta: artículo vendido, cantidad y precio de venta aplicado.
 * `costo_unitario_historico` congela el CMP al momento de la venta para el
 * asiento de Costo de Ventas (nunca se recalcula retroactivamente).
 *
 * @property int $id
 * @property int $cantidad
 * @property float $precio_unitario
 * @property float $costo_unitario_historico
 */
class VentaDetalle extends Model
{
    protected $fillable = [
        'venta_id',
        'articulo_id',
        'cantidad',
        'precio_unitario',
        'costo_unitario_historico',
        'subtotal',
    ];

    /**
     * Casting de variables financieras.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'costo_unitario_historico' => 'decimal:4',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * Venta a la que pertenece la línea.
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Artículo vendido.
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }
}
