<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory de proveedores para pruebas.
 *
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    /**
     * Define el estado por defecto del proveedor (nacional).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_razon_social' => 'Proveedor '.$this->faker->unique()->company(),
            'nit' => (string) $this->faker->unique()->numberBetween(1000000, 999999999),
            'pais' => 'Bolivia',
            'contacto' => $this->faker->email(),
        ];
    }
}
