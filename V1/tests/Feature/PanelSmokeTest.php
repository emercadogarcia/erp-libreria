<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Humo del panel Filament: todas las páginas del panel deben renderizar 200
 * con un administrador autenticado (recursos + informes contables).
 */
class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanCuentasSeeder::class);
        $this->seed(\Database\Seeders\DemoDataSeeder::class);

        $this->admin = User::where('email', 'admin@libreria.bo')->firstOrFail();
    }

    /**
     * Páginas que deben renderizar sin errores.
     */
    #[Test]
    public function paginas_del_panel_renderizan_200(): void
    {
        $rutas = [
            '/admin',
            '/admin/articulos',
            '/admin/articulos/create',
            '/admin/clientes',
            '/admin/proveedores',
            '/admin/ventas',
            '/admin/ventas/create',
            '/admin/compras',
            '/admin/compras/create',
            '/admin/caja-diarias',
            '/admin/ajustes-inventario',
            '/admin/ajustes-inventario/create',
            '/admin/libro-diario',
            '/admin/estado-resultados',
        ];

        foreach ($rutas as $ruta) {
            $this->actingAs($this->admin)->get($ruta)->assertOk();
        }
    }

    /**
     * El dashboard muestra los KPIs del negocio.
     */
    #[Test]
    public function dashboard_muestra_kpis(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Valorización de inventario');
    }
}
