<?php

namespace App\Filament\Pages;

use App\Services\AccountingService;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;

/**
 * Informe del Libro Diario filtrado por fechas (PRD Módulo 5).
 * Muestra asientos con líneas Debe/Haber y verifica la ecuación de partida
 * doble en cada asiento (Debe - Haber = 0).
 */
class LibroDiario extends Page
{
    public ?string $desde = null;

    public ?string $hasta = null;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static string $view = 'filament.pages.libro-diario';

    protected static ?string $navigationGroup = 'Contabilidad';

    protected static ?string $title = 'Libro Diario';

    /**
     * Filtro de rango de fechas.
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                \Filament\Forms\Form::make($this)
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->columns(2),
            ),
        ];
    }

    /**
     * Asientos del rango seleccionado.
     */
    public function getAsientosProperty()
    {
        return AccountingService::libroDiario($this->desde, $this->hasta);
    }

    /**
     * Totales del período para verificar el balance general.
     */
    public function getTotalesProperty(): array
    {
        $asientos = $this->asientos;

        return [
            'debe' => round($asientos->sum('total_debe'), 2),
            'haber' => round($asientos->sum('total_haber'), 2),
            'cuadrado' => $asientos->every(fn ($a) => $a->estaCuadrado()),
        ];
    }
}
