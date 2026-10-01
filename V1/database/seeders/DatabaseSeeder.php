<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orquestador de seeders del MVP: Plan de Cuentas Bolivia + datos demo.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanCuentasSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
