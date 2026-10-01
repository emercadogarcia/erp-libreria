<?php

namespace App\Filament\Pages;

use App\Services\AccountingService;
use Filament\Pages\Page;

/**
 * Estado de Resultados dinámico preliminar (PRD Módulo 5):
 * Ventas netas - Costo de Ventas = Utilidad Bruta; - Gastos = Utilidad Neta.
 */
class EstadoResultados extends Page
{
    public ?string $desde = null;

    public ?string $hasta = null;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.estado-resultados';

    protected static ?string $navigationGroup = 'Contabilidad';

    protected static ?string $title = 'Estado de Resultados';

    /**
     * Cifras del período.
     */
    public function getResultadoProperty(): array
    {
        return AccountingService::estadoResultados($this->desde, $this->hasta);
    }
}
