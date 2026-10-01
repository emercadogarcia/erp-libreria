<?php

namespace App\Models;

use Database\Factories\ArticuloFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Artículo de la librería: libro o material de escritorio (PRD Módulo 4).
 * Concentra el stock físico y las variables financieras del CMP (Costo Medio
 * Ponderado) y el Precio de Venta (P.V.), recalculados por InventoryService.
 *
 * @property int $id
 * @property string $codigo_sistema
 * @property string $titulo_nombre
 * @property ?string $autor
 * @property ?string $editorial
 * @property int $stock_actual
 * @property int $stock_minimo
 * @property float $costo_medio_ponderado
 * @property float $precio_venta
 */
class Articulo extends Model
{
    /** @use HasFactory<ArticuloFactory> */
    use HasFactory;

    protected $table = 'articulos';

    protected $fillable = [
        'codigo_sistema',
        'titulo_nombre',
        'autor',
        'editorial',
        'categoria_id',
        'tipo',
        'stock_actual',
        'stock_minimo',
        'costo_medio_ponderado',
        'precio_venta',
        'es_gnd',
        'activo',
    ];

    /**
     * Casting de variables financieras y operativas.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock_actual' => 'integer',
            'stock_minimo' => 'integer',
            'costo_medio_ponderado' => 'decimal:4', // Precisión interna 14,4 (PRD estándar); 14,2 solo en presentación
            'precio_venta' => 'decimal:2',
            'es_gnd' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * Categoría a la que pertenece el artículo.
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Líneas de compra donde aparece el artículo.
     */
    public function compraDetalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class);
    }

    /**
     * Líneas de venta donde aparece el artículo.
     */
    public function ventaDetalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    /**
     * Ajustes de inventario aplicados al artículo.
     */
    public function ajustes(): HasMany
    {
        return $this->hasMany(AjusteInventario::class);
    }
}
