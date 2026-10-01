<?php

namespace App\Filament\Resources\ArticuloResource\Pages;

use App\Filament\Resources\ArticuloResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Listado de artículos del inventario.
 */
class ListArticulos extends ListRecords
{
    protected static string $resource = ArticuloResource::class;
}
