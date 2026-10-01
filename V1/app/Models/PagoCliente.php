<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de crédito/cuota de un cliente (PRD Módulo 2: cobranzas básicas).
 *
 * @property int $id
 * @property float $monto
 */
class PagoCliente extends Model
{
    protected $table = 'pagos_cliente';

    protected $fillable = ['venta_id', 'cliente_id', 'monto', 'metodo', 'observacion', 'registrado_por'];

    /**
     * Casting de variables financieras.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    /**
     * Venta a crédito que se está pagando.
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Cliente que realiza el pago.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
