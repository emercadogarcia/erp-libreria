<?php

namespace App\Filament\Resources\CompraResource\Pages;

use App\Filament\Resources\CompraResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

/**
 * Vista de detalle de compra con impacto del prorrateo por línea.
 */
class ViewCompras extends ViewRecord
{
    protected static string $resource = CompraResource::class;

    /**
     * Infolist con detalle de líneas (FOB vs costo final prorrateado).
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('codigo')->label('Código')->fontFamily('mono'),
            TextEntry::make('proveedor.nombre_razon_social')->label('Proveedor'),
            TextEntry::make('tipo_compra')->label('Tipo')->badge(),
            TextEntry::make('estado_flujo')->label('Etapa del flujo')->badge()
                ->formatStateUsing(fn (string $state) => CompraResource::etiquetaEstado($state)),
            TextEntry::make('fecha_emision')->date('d/m/Y'),
            TextEntry::make('es_gnd')->label('Tratamiento fiscal')->badge()
                ->formatStateUsing(fn (bool $state) => $state ? 'GND (sin crédito fiscal)' : 'Deducible (crédito fiscal 13%)')
                ->color(fn (bool $state) => $state ? 'danger' : 'success'),
            TextEntry::make('valor_fob')->label('Valor FOB (F.fob)')->money('Bs'),
            TextEntry::make('costos_adicionales_prorrateo')->label('Costos adicionales (prorrateo)')->money('Bs'),
            RepeatableEntry::make('detalles')
                ->label('Detalle de líneas (costo FOB vs costo final prorrateado)')
                ->schema([
                    TextEntry::make('articulo.titulo_nombre')->label('Artículo'),
                    TextEntry::make('cantidad'),
                    TextEntry::make('costo_unitario_fob')->label('Costo FOB')->money('Bs'),
                    TextEntry::make('costo_unitario_final')->label('Costo final (prorrateado)')->money('Bs')->weight('bold'),
                ])
                ->columns(4)
                ->columnSpanFull(),
            TextEntry::make('total_fob')->label('Total FOB de líneas')->money('Bs'),
            TextEntry::make('total_final')->label('Total final (con prorrateo)')->money('Bs')->weight('bold'),
        ]);
    }
}
