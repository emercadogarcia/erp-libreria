<?php

namespace App\Filament\Widgets;

use App\Models\Articulo;
use App\Models\Venta;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPIs del dashboard (PRD Módulos 2 y 4): valorización de inventario,
 * ventas del día, pendientes de validación y alertas de quiebre de stock.
 */
class DashboardStats extends BaseWidget
{
    // Renderizado en servidor (sin lazy-load) para que los KPIs estén en el HTML inicial
    protected static bool $isLazy = false;
    /**
     * Tarjetas de indicadores.
     *
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        // Valorización total: stock * CMP por artículo
        $valorizacion = Articulo::query()->selectRaw('COALESCE(SUM(stock_actual * costo_medio_ponderado), 0)')->value('stock_actual * costo_medio_ponderado') ?? 0;

        $ventasHoy = Venta::whereDate('created_at', today())->where('estado', 'Aprobada')->sum('total');
        $pendientes = Venta::where('estado', 'PendienteValidacion')->count();
        $stockBajo = Articulo::whereColumn('stock_actual', '<=', 'stock_minimo')->count();

        return [
            Stat::make('Valorización de inventario', 'Bs '.number_format((float) $valorizacion, 2))
                ->description('Stock * CMP (Costo Medio Ponderado)')
                ->icon('heroicon-o-cube'),
            Stat::make('Ventas aprobadas hoy', 'Bs '.number_format((float) $ventasHoy, 2))
                ->description('Web + mostrador')
                ->icon('heroicon-o-shopping-cart'),
            Stat::make('Pendientes de validación', (string) $pendientes)
                ->description('Ventas web por revisar comprobante')
                ->color($pendientes > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock'),
            Stat::make('Artículos bajo stock mínimo', (string) $stockBajo)
                ->description('Alerta de quiebre de stock')
                ->color($stockBajo > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }
}
