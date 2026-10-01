<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory de clientes para pruebas.
 *
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /**
     * Define el estado por defecto del cliente.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_razon_social' => $this->faker->name(),
            'nit_ci' => (string) $this->faker->unique()->numberBetween(1000000, 99999999),
            'email' => $this->faker->email(),
            'telefono' => $this->faker->phoneNumber(),
            'limite_credito' => 1000,
        ];
    }
}
