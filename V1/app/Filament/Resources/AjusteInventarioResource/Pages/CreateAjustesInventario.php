<?php

namespace App\Filament\Resources\AjusteInventarioResource\Pages;

use App\Filament\Resources\AjusteInventarioResource;
use App\Services\InventoryService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creación de ajustes vía InventoryService (stock + CMP + asiento en una sola
 * transacción, PRD Sección 6: seguridad financiera).
 */
class CreateAjustesInventario extends CreateRecord
{
    protected static string $resource = AjusteInventarioResource::class;

    /**
     * Delegación al servicio transaccional del inventario.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return InventoryService::registrarAjuste($data);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo registrar el ajuste')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        Notification::make()
            ->title('Ajuste registrado: stock, CMP/P.V. y asiento contable actualizados')
            ->success()
            ->send();

        return static::getResource()::getUrl('index');
    }
}
