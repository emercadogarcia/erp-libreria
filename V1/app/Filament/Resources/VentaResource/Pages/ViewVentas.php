<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

/**
 * Vista de detalle de venta con comprobante e historial de pagos.
 */
class ViewVentas extends ViewRecord
{
    protected static string $resource = VentaResource::class;

    /**
     * Infolist con datos de la venta, comprobante cargado (web) y pagos de crédito.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('numero_recibo')->label('Recibo')->fontFamily('mono'),
            TextEntry::make('origen')->label('Origen')->badge(),
            TextEntry::make('estado')->label('Estado')->badge(),
            TextEntry::make('forma_pago')->label('Forma de pago')->badge(),
            TextEntry::make('cliente_nombre')->label('Cliente')->default('—'),
            TextEntry::make('cliente_nit_ci')->label('NIT/CI')->default('—'),
            TextEntry::make('cliente_email')->label('Email')->default('—'),
            TextEntry::make('subtotal')->money('Bs'),
            TextEntry::make('descuento')->money('Bs'),
            TextEntry::make('total')->money('Bs')->weight('bold'),
            ImageEntry::make('comprobante_path')
                ->label('Comprobante de pago (web)')
                ->disk('public')
                ->visible(fn ($record) => filled($record->comprobante_path))
                ->columnSpanFull(),
            RepeatableEntry::make('detalles')
                ->label('Detalle de ítems')
                ->schema([
                    TextEntry::make('articulo.titulo_nombre')->label('Artículo'),
                    TextEntry::make('cantidad'),
                    TextEntry::make('precio_unitario')->money('Bs'),
                    TextEntry::make('subtotal')->money('Bs'),
                ])
                ->columns(4)
                ->columnSpanFull(),
            RepeatableEntry::make('pagos')
                ->label('Historial de pagos de crédito')
                ->schema([
                    TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
                    TextEntry::make('monto')->money('Bs'),
                    TextEntry::make('metodo')->badge(),
                    TextEntry::make('observacion')->default('—'),
                ])
                ->columns(4)
                ->columnSpanFull()
                ->visible(fn ($record) => $record->pagos->isNotEmpty()),
        ]);
    }
}
