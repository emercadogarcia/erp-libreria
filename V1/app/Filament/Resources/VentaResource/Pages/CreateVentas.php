<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use App\Services\SalesService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creación de ventas de mostrador (POS) desde el formulario del recurso.
 */
class CreateVentas extends CreateRecord
{
    protected static string $resource = VentaResource::class;

    /**
     * El alta de ventas se delega a SalesService (stock + contabilidad).
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $items = collect($data['items'] ?? [])->map(fn ($i) => [
            'articulo_id' => (int) $i['articulo_id'],
            'cantidad' => (int) $i['cantidad'],
        ])->all();

        return SalesService::registrarVentaMostrador(
            items: $items,
            clienteDatos: $data['cliente_datos'] ?? [],
            formaPago: $data['forma_pago'] ?? 'Efectivo',
            clienteId: $data['cliente_id'] ?? null,
        );
    }

    /**
     * Redirige al listado tras registrar la venta.
     */
    protected function getRedirectUrl(): string
    {
        Notification::make()
            ->title('Venta registrada y contabilizada')
            ->success()
            ->send();

        return static::getResource()::getUrl('index');
    }
}
