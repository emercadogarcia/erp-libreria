<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de detalle de una compra: artículo, cantidad y costos.
 * `costo_unitario_final` se recalcula automáticamente tras el prorrateo.
 *
 * @property int $id
 * @property int $cantidad
 * @property float $costo_unitario_fob
 * @property float $costo_unitario_final
 */
class CompraDetalle extends Model
{
    protected $fillable = [
        'compra_cabecera_id',
        'articulo_id',
        'cantidad',
        'costo_unitario_fob',
        'costo_unitario_final',
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
            'costo_unitario_fob' => 'decimal:4',
            'costo_unitario_final' => 'decimal:4',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * Compra a la que pertenece la línea.
     */
    public function compra(): BelongsTo
    {
        return $this->belongsTo(CompraCabecera::class, 'compra_cabecera_id');
    }

    /**
     * Artículo adquirido.
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }
}
