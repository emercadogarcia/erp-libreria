<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Factura electrónica SIMULADA (PRD Módulo 6).
 * Guarda los campos exigidos por el SIN (CUFD, CUIS, Código de Control y
 * firma digital teórica) sin conexión real a los Web Services.
 *
 * @property int $id
 * @property int $venta_id
 * @property string $numero_factura
 * @property string $cuis
 * @property string $cufd
 * @property string $cuf
 * @property string $codigo_control
 * @property string $firma_digital_hash
 * @property float $total_base_credito_fiscal
 * @property float $debito_fiscal
 * @property string $leyenda
 */
class Factura extends Model
{
    protected $fillable = [
        'venta_id',
        'numero_factura',
        'cuis',
        'cufd',
        'cuf',
        'codigo_control',
        'firma_digital_hash',
        'fecha_emision',
        'total_base_credito_fiscal',
        'debito_fiscal',
        'leyenda',
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
            'total_base_credito_fiscal' => 'decimal:2',
            'debito_fiscal' => 'decimal:2',
        ];
    }

    /**
     * Venta facturada.
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }
}
