<?php

namespace App\Filament\Resources\ArticuloResource\Pages;

use App\Filament\Resources\ArticuloResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creación de artículos con recálculo inicial del P.V. según margen.
 */
class CreateArticulo extends CreateRecord
{
    protected static string $resource = ArticuloResource::class;

    /**
     * Al crear un artículo con CMP, el P.V. se recalcula con el margen parametrizado.
     */
    protected function afterCreate(): void
    {
        $articulo = $this->record;

        if ((float) $articulo->costo_medio_ponderado > 0) {
            \App\Services\InventoryService::recalcularPrecioVenta($articulo);
        }
    }
}
