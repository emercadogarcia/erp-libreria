<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cuenta del Plan de Cuentas estándar para el rubro librería en Bolivia.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $tipo
 * @property string $naturaleza
 * @property bool $es_imputable
 */
class PlanCuenta extends Model
{
    protected $fillable = ['codigo', 'nombre', 'tipo', 'naturaleza', 'es_imputable'];

    /**
     * Casting de flags.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_imputable' => 'boolean',
        ];
    }
}
