<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use App\Services\SalesService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Listado de ventas con pestañas por estado y acción de POS de mostrador.
 */
class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    /**
     * Acción de cabecera: Punto de Venta (POS) interno simplificado.
     */
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('pos')
                ->label('POS - Venta de Mostrador')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->form(fn () => VentaResource::posFormSchema())
                ->action(function (array $data) {
                    // Las ventas a crédito requieren cliente registrado
                    $items = collect($data['items'] ?? [])->map(fn ($i) => [
                        'articulo_id' => (int) $i['articulo_id'],
                        'cantidad' => (int) $i['cantidad'],
                    ])->all();

                    $venta = SalesService::registrarVentaMostrador(
                        items: $items,
                        clienteDatos: $data['cliente_datos'] ?? [],
                        formaPago: $data['forma_pago'] ?? 'Efectivo',
                        clienteId: $data['cliente_id'] ?? null,
                    );

                    Notification::make()
                        ->title('Venta registrada: '.$venta->numero_recibo)
                        ->body('Stock descontado y asiento contable generado.')
                        ->success()
                        ->send();
                })
                ->modalWidth('2xl'),
        ];
    }

    /**
     * Pestañas de filtrado rápido por estado.
     */
    public function getTabs(): array
    {
        return [
            'todas' => Tab::make('Todas'),
            'pendientes' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', 'PendienteValidacion'))
                ->badge(\App\Models\Venta::where('estado', 'PendienteValidacion')->count())
                ->badgeColor('warning'),
            'aprobadas' => Tab::make('Aprobadas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', 'Aprobada')),
            'credito' => Tab::make('A Crédito')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('es_credito', true)->where('estado', 'Aprobada')),
        ];
    }
}
