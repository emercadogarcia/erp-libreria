<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cabecera del flujo secuencial de compra (PRD Módulo 3).
 * Estados inalterables: Cotizacion -> Oferta -> OrdenCompra -> NotaIngreso ->
 * Facturado -> GastoImportacion -> Pagado.
 *
 * @property int $id
 * @property string $codigo
 * @property string $tipo_compra
 * @property string $estado_flujo
 * @property int $proveedor_id
 * @property float $valor_fob
 * @property float $costos_adicionales_prorrateo
 * @property bool $es_gnd
 */
class CompraCabecera extends Model
{
    protected $table = 'compras_cabecera';

    /** Estados del flujo secuencial, en orden estricto. */
    public const ESTADOS = [
        'Cotizacion',
        'Oferta',
        'OrdenCompra',
        'NotaIngreso',
        'Facturado',
        'GastoImportacion',
        'Pagado',
    ];

    protected $fillable = [
        'codigo',
        'tipo_compra',
        'estado_flujo',
        'proveedor_id',
        'fecha_emision',
        'fecha_estimada_recepcion',
        'valor_fob',
        'costos_adicionales_prorrateo',
        'es_gnd',
        'glosa',
    ];

    /**
     * Casting de variables financieras.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_estimada_recepcion' => 'date',
            'valor_fob' => 'decimal:2',
            'costos_adicionales_prorrateo' => 'decimal:2',
            'es_gnd' => 'boolean',
        ];
    }

    /**
     * Proveedor de la compra.
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /**
     * Líneas de detalle (artículos, cantidades y costos).
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'compra_cabecera_id');
    }

    /**
     * Valor FOB total de la compra (suma de líneas).
     */
    public function getTotalFobAttribute(): float
    {
        return (float) $this->detalles->sum(fn (CompraDetalle $d) => $d->cantidad * $d->costo_unitario_fob);
    }

    /**
     * Costo total final tras prorrateo (FOB + costos adicionales).
     */
    public function getTotalFinalAttribute(): float
    {
        return (float) $this->detalles->sum(fn (CompraDetalle $d) => $d->cantidad * $d->costo_unitario_final);
    }
}
