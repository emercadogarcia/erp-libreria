<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ajuste de inventario (PRD Módulo 4): Donación, Compra GND o Pérdida/Deterioro.
 * Las donaciones afectan stock sin modificar el CMP; las compras GND asignan
 * costo directo; las pérdidas egresan stock y generan gasto contable.
 *
 * @property int $id
 * @property string $tipo
 * @property int $cantidad
 * @property bool $afecta_cmp
 */
class AjusteInventario extends Model
{
    protected $table = 'ajustes_inventario';

    public const TIPOS = ['Donacion', 'CompraGND', 'PerdidaDeterioro'];

    protected $fillable = [
        'codigo',
        'tipo',
        'articulo_id',
        'cantidad',
        'costo_unitario',
        'afecta_cmp',
        'motivo',
        'user_id',
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
            'costo_unitario' => 'decimal:4',
            'afecta_cmp' => 'boolean',
        ];
    }

    /**
     * Artículo ajustado.
     */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    /**
     * Usuario que registró el ajuste.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
