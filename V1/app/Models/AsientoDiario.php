<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cabecera de asiento del Libro Diario (PRD Módulo 5).
 * Todo asiento debe cumplir la ecuación de partida doble: Debe - Haber = 0.
 *
 * @property int $id
 * @property string $numero_asiento
 * @property string $glosa
 * @property float $total_debe
 * @property float $total_haber
 * @property string $origen
 * @property ?int $referencia_id
 */
class AsientoDiario extends Model
{
    protected $table = 'asientos_diario';

    protected $fillable = [
        'numero_asiento',
        'glosa',
        'fecha_asiento',
        'total_debe',
        'total_haber',
        'origen',
        'referencia_id',
    ];

    /**
     * Casting de variables financieras.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_asiento' => 'datetime',
            'total_debe' => 'decimal:2',
            'total_haber' => 'decimal:2',
        ];
    }

    /**
     * Líneas del asiento (partida doble).
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(AsientoDetalle::class);
    }

    /**
     * Verifica el equilibrio de partida doble (Debe - Haber = 0, tolerancia 0.01).
     */
    public function estaCuadrado(): bool
    {
        return abs((float) $this->total_debe - (float) $this->total_haber) < 0.0001;
    }
}
