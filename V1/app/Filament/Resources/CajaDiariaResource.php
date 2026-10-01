<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CajaDiariaResource\Pages;
use App\Models\CajaDiaria;
use App\Services\SalesService;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Recurso Filament de apertura y cierre de caja diaria en Bs. (PRD Módulo 2).
 */
class CajaDiariaResource extends Resource
{
    protected static ?string $model = CajaDiaria::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Ventas y Caja';

    protected static ?string $modelLabel = 'Caja Diaria';

    protected static ?string $pluralModelLabel = 'Cajas Diarias';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('monto_apertura')->label('Monto de apertura')->numeric()->prefix('Bs')->default(0)->required(),
                TextInput::make('monto_cierre')->label('Monto de cierre')->numeric()->prefix('Bs'),
            ]),
            TextInput::make('observaciones')->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')->date('d/m/Y')->sortable(),
                TextColumn::make('responsable.name')->label('Responsable'),
                TextColumn::make('monto_apertura')->label('Apertura')->money('Bs'),
                TextColumn::make('ventas_efectivo')->label('Ventas efectivo')->money('Bs'),
                TextColumn::make('ventas_qr')->label('Ventas QR')->money('Bs'),
                TextColumn::make('total_teorico')->label('Total teórico')->money('Bs'),
                TextColumn::make('monto_cierre')->label('Cierre')->money('Bs')->placeholder('—'),
                TextColumn::make('diferencia')->label('Diferencia')->money('Bs')
                    ->color(fn ($state) => (float) $state == 0 ? 'success' : ((float) $state > 0 ? 'info' : 'danger'))
                    ->placeholder('—'),
                TextColumn::make('estado')->badge()
                    ->color(fn (string $state) => $state === 'Abierta' ? 'warning' : 'success'),
            ])
            ->actions([
                Action::make('cerrar')
                    ->label('Cerrar caja')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (CajaDiaria $record) => $record->estado === 'Abierta')
                    ->form([
                        TextInput::make('monto_cierre')
                            ->label('Monto físico contado en caja')
                            ->numeric()
                            ->prefix('Bs')
                            ->required(),
                        TextInput::make('observaciones')->maxLength(500),
                    ])
                    ->action(function (CajaDiaria $record, array $data) {
                        SalesService::cerrarCaja($record, (float) $data['monto_cierre'], $data['observaciones'] ?? '');

                        \Filament\Notifications\Notification::make()
                            ->title('Caja cerrada. Diferencia de arqueo: Bs '.number_format($record->diferencia, 2))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('fecha', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCajasDiarias::route('/'),
        ];
    }
}
