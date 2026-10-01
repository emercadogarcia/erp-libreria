<?php

namespace App\Filament\Resources\ArticuloResource\Pages;

use App\Filament\Resources\ArticuloResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edición de artículos con acción de recálculo del P.V. según margen.
 */
class EditArticulo extends EditRecord
{
    protected static string $resource = ArticuloResource::class;

    /**
     * Acciones de cabecera: recalcular precio con el margen parametrizado.
     */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('recalcularPrecio')
                ->label('Recalcular P.V. según margen')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    $nuevoPrecio = \App\Services\InventoryService::recalcularPrecioVenta($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Precio recalculado')
                        ->body('Nuevo P.V.: Bs. '.number_format($nuevoPrecio, 2))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
