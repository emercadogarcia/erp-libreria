<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Listado de compras con flujo secuencial.
 */
class ListCompras extends ListRecords
{
    protected static string $resource = CompraResource::class;
}
