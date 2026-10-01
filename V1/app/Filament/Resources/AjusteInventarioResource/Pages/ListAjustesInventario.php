<?php

namespace App\Filament\Resources\AjusteInventarioResource\Pages;

use App\Filament\Resources\AjusteInventarioResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Listado de ajustes de inventario.
 */
class ListAjustesInventario extends ListRecords
{
    protected static string $resource = AjusteInventarioResource::class;
}
