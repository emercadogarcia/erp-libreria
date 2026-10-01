<?php

namespace Database\Seeders;

use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder de datos demo del MVP: usuario admin de Filament, catálogo de
 * libros y material de escritorio, clientes y proveedores.
 * Precios calculados con el margen parametrizado (config/accounting.php).
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Ejecuta la carga de datos demo (idempotente: firstOrCreate).
     */
    public function run(): void
    {
        // Usuario administrador del panel Filament
        User::firstOrCreate(
            ['email' => 'admin@libreria.bo'],
            ['name' => 'Administrador', 'password' => bcrypt('admin123')]
        );

        // Categorías minimalistas del catálogo
        $catLibros = Categoria::firstOrCreate(['nombre' => 'Libros']);
        $catEscritorio = Categoria::firstOrCreate(['nombre' => 'Material de Escritorio']);

        // Catálogo demo: libros (autor + editorial) y material de escritorio
        $libros = [
            ['titulo_nombre' => 'Cien Años de Soledad', 'autor' => 'Gabriel García Márquez', 'editorial' => 'Sudamericana', 'costo' => 45.00, 'stock' => 20],
            ['titulo_nombre' => 'La Razón de mi Vida', 'autor' => 'Eva Perón', 'editorial' => 'Peuser', 'costo' => 35.00, 'stock' => 15],
            ['titulo_nombre' => 'Un Mundo para Julius', 'autor' => 'Alfredo Bryce Echenique', 'editorial' => 'Seix Barral', 'costo' => 50.00, 'stock' => 12],
            ['titulo_nombre' => 'Monte Pensativo', 'autor' => 'Gaby Vallejo Canedo', 'editorial' => 'Editorial Plus', 'costo' => 40.00, 'stock' => 18],
            ['titulo_nombre' => 'Historia de Bolivia', 'autor' => 'Roberto Querejazu', 'editorial' => 'Los Amigos del Libro', 'costo' => 60.00, 'stock' => 10],
            ['titulo_nombre' => 'Matemática 5° Secundaria', 'autor' => 'Editorial Santillana', 'editorial' => 'Santillana', 'costo' => 38.00, 'stock' => 25],
        ];

        foreach ($libros as $libro) {
            // Clave de idempotencia: título (el código se genera solo al crear)
            $articulo = Articulo::firstOrCreate(
                ['titulo_nombre' => $libro['titulo_nombre']],
                [
                    'codigo_sistema' => 'LIB-'.str_pad((string) ((int) Articulo::max('id') + 1), 5, '0', STR_PAD_LEFT),
                    'autor' => $libro['autor'],
                    'editorial' => $libro['editorial'],
                    'categoria_id' => $catLibros->id,
                    'tipo' => 'libro',
                    'stock_actual' => $libro['stock'],
                    'stock_minimo' => 5,
                    'costo_medio_ponderado' => $libro['costo'],
                ]
            );

            // P.V. según margen parametrizable (30% por defecto)
            $articulo->precio_venta = round($articulo->costo_medio_ponderado * (1 + config('accounting.margen_ganancia')), 2);
            $articulo->save();
        }

        $escritorio = [
            ['titulo_nombre' => 'Cuaderno Profesional 100 Hojas', 'editorial' => null, 'costo' => 8.50, 'stock' => 100],
            ['titulo_nombre' => 'Bolígrafo Azul (caja x 50)', 'editorial' => 'Faber-Castell', 'costo' => 45.00, 'stock' => 40],
            ['titulo_nombre' => 'Resma Papel Bond Carta 500h', 'editorial' => 'Chamex', 'costo' => 25.00, 'stock' => 60],
            ['titulo_nombre' => 'Calculadora Científica FX-991', 'editorial' => 'Casio', 'costo' => 180.00, 'stock' => 8],
        ];

        foreach ($escritorio as $item) {
            $articulo = Articulo::firstOrCreate(
                ['titulo_nombre' => $item['titulo_nombre']],
                [
                    'codigo_sistema' => 'ESC-'.str_pad((string) ((int) Articulo::max('id') + 1), 5, '0', STR_PAD_LEFT),
                    'autor' => null,
                    'editorial' => $item['editorial'],
                    'categoria_id' => $catEscritorio->id,
                    'tipo' => 'escritorio',
                    'stock_actual' => $item['stock'],
                    'stock_minimo' => 10,
                    'costo_medio_ponderado' => $item['costo'],
                ]
            );

            $articulo->precio_venta = round($articulo->costo_medio_ponderado * (1 + config('accounting.margen_ganancia')), 2);
            $articulo->save();
        }

        // Clientes demo (con y sin crédito)
        Cliente::firstOrCreate(['nit_ci' => '6345872'], [
            'nombre_razon_social' => 'Colegio San Calixto',
            'email' => 'compras@sancalixto.edu.bo',
            'telefono' => '+591 2 2458796',
            'limite_credito' => 5000.00,
        ]);

        Cliente::firstOrCreate(['nit_ci' => '3389145'], [
            'nombre_razon_social' => 'Juan Pérez Mamani',
            'email' => 'jperez@gmail.com',
            'telefono' => '+591 70012345',
            'limite_credito' => 500.00,
        ]);

        // Proveedores demo (nacional e internacional)
        Proveedor::firstOrCreate(['nombre_razon_social' => 'Distribuidora Los Amigos del Libro'], [
            'nit' => '1025874015',
            'pais' => 'Bolivia',
            'contacto' => 'ventas@losamigoslibro.bo',
        ]);

        Proveedor::firstOrCreate(['nombre_razon_social' => 'Penguin Random House Grupo Editorial'], [
            'nit' => 'EXT-PRH-001',
            'pais' => 'España',
            'contacto' => 'exports@penguinrandomhouse.es',
        ]);

        Proveedor::firstOrCreate(['nombre_razon_social' => 'Papelería e Importaciones Andina S.R.L.'], [
            'nit' => '4589623018',
            'pais' => 'Bolivia',
            'contacto' => 'comercial@andina.bo',
        ]);
    }
}
