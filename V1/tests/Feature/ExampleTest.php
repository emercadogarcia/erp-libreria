<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba básica de humo: el catálogo público responde correctamente.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La home del catálogo responde 200 con el catálogo cargado.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seed(\Database\Seeders\PlanCuentasSeeder::class);
        $this->seed(\Database\Seeders\DemoDataSeeder::class);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
