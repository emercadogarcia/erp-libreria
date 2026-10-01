<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea del asiento contable (partida doble).
 * `debe` y `haber` son mutuamente excluyentes: una línea solo afecta un lado.
 *
 * @property int $id
 * @property string $cuenta_codigo
 * @property float $debe
 * @property float $haber
 */
class AsientoDetalle extends Model
{
    protected $fillable = ['asiento_diario_id', 'cuenta_codigo', 'debe', 'haber'];

    /**
     * Casting de variables financieras.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debe' => 'decimal:2',
            'haber' => 'decimal:2',
        ];
    }

    /**
     * Asiento al que pertenece la línea.
     */
    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoDiario::class);
    }

    /**
     * Cuenta contable del plan de cuentas.
     */
    public function planCuenta(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_codigo', 'codigo');
    }
}
