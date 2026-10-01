<?php

namespace App\Filament\Resources\CajaDiariaResource\Pages;

use App\Filament\Resources\CajaDiariaResource;
use App\Services\SalesService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;

/**
 * Listado de cajas diarias con acción de apertura.
 */
class ListCajasDiarias extends ListRecords
{
    protected static string $resource = CajaDiariaResource::class;

    /**
     * Acción de cabecera: apertura de caja del día.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('abrirCaja')
                ->label('Apertura de caja')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\TextInput::make('monto_apertura')
                        ->label('Monto de apertura')
                        ->numeric()
                        ->prefix('Bs')
                        ->default(0)
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        SalesService::abrirCaja((float) $data['monto_apertura']);

                        Notification::make()->title('Caja abierta para hoy')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
