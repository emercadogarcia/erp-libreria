<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cliente de la librería (persona o empresa) con control de crédito.
 *
 * @property int $id
 * @property string $nombre_razon_social
 * @property string $nit_ci
 * @property float $limite_credito
 */
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = ['nombre_razon_social', 'nit_ci', 'email', 'telefono', 'limite_credito'];

    /**
     * Casting de variables financieras.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limite_credito' => 'decimal:2',
        ];
    }

    /**
     * Ventas asociadas al cliente.
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /**
     * Historial de pagos de crédito del cliente.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(PagoCliente::class);
    }
}
