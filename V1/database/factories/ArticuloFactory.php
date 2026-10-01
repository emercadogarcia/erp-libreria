<?php

namespace Database\Factories;

use App\Models\Articulo;
use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory de artículos para pruebas.
 *
 * @extends Factory<Articulo>
 */
class ArticuloFactory extends Factory
{
    protected $model = Articulo::class;

    /**
     * Define el estado por defecto del artículo (libro genérico).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $costo = $this->faker->randomFloat(2, 10, 200);

        return [
            'codigo_sistema' => 'ART-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'titulo_nombre' => 'Libro de Prueba '.$this->faker->unique()->numberBetween(1, 9999),
            'autor' => $this->faker->name(),
            'editorial' => 'Editorial Prueba',
            'categoria_id' => Categoria::factory(),
            'tipo' => 'libro',
            'stock_actual' => 10,
            'stock_minimo' => 5,
            'costo_medio_ponderado' => $costo,
            'precio_venta' => round($costo * 1.3, 2),
            'activo' => true,
        ];
    }
}
