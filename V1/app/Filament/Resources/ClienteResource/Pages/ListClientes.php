<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Listado de clientes.
 */
class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;
}
