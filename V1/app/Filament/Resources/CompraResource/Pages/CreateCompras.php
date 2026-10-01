<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creación de compras (arranca el flujo en estado Cotización).
 */
class CreateCompras extends CreateRecord
{
    protected static string $resource = CompraResource::class;

    /**
     * Alta con código correlativo y estado inicial del flujo.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['codigo'] = 'COMP-'.now()->year.'-'.str_pad((string) ((int) \App\Models\CompraCabecera::max('id') + 1), 5, '0', STR_PAD_LEFT);
        $data['estado_flujo'] = 'Cotizacion';
        $data['fecha_emision'] ??= now()->toDateString();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        Notification::make()->title('Compra creada en etapa Cotización')->success()->send();

        return static::getResource()::getUrl('index');
    }
}
