<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apertura y cierre de caja diario en Bs. (PRD Módulo 2).
 *
 * @property int $id
 * @property string $fecha
 * @property float $monto_apertura
 * @property ?float $monto_cierre
 * @property string $estado
 */
class CajaDiaria extends Model
{
    protected $table = 'caja_diarias';

    protected $fillable = [
        'fecha',
        'user_id',
        'monto_apertura',
        'monto_cierre',
        'ventas_efectivo',
        'ventas_qr',
        'diferencia',
        'estado',
        'observaciones',
    ];

    /**
     * Casting de variables financieras.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto_apertura' => 'decimal:2',
            'monto_cierre' => 'decimal:2',
            'ventas_efectivo' => 'decimal:2',
            'ventas_qr' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    /**
     * Responsable de caja.
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Total teórico que debería haber en caja al cierre (apertura + cobros).
     */
    public function getTotalTeoricoAttribute(): float
    {
        return round($this->monto_apertura + $this->ventas_efectivo + $this->ventas_qr, 2);
    }
}
