<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AjusteInventarioResource\Pages;
use App\Models\AjusteInventario;
use App\Services\InventoryService;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Recurso Filament de ajustes de inventario (PRD Módulo 4):
 *  - Ingreso por Donación: afecta stock SIN modificar costos promedio.
 *  - Ingreso por Compra GND: afecta stock, costo asignado directo.
 *  - Egreso / Ajuste por Pérdida o Deterioro.
 */
class AjusteInventarioResource extends Resource
{
    protected static ?string $model = AjusteInventario::class;

    protected static ?string $slug = 'ajustes-inventario';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $modelLabel = 'Ajuste de Inventario';

    protected static ?string $pluralModelLabel = 'Ajustes de Inventario';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('tipo')
                ->options([
                    'Donacion' => 'Ingreso por Donación (no altera CMP)',
                    'CompraGND' => 'Ingreso por Compra GND (costo directo)',
                    'PerdidaDeterioro' => 'Egreso por Pérdida o Deterioro',
                ])
                ->live()
                ->required(),
            Select::make('articulo_id')
                ->label('Artículo')
                ->relationship('articulo', 'titulo_nombre')
                ->searchable()
                ->preload()
                ->required(),
            Grid::make(2)->schema([
                TextInput::make('cantidad')
                    ->numeric()
                    ->required()
                    ->helperText('Positivo = ingreso, negativo = egreso. La pérdida solo admite negativos.'),
                TextInput::make('costo_unitario')
                    ->label('Costo unitario asignado')
                    ->numeric()
                    ->prefix('Bs')
                    ->default(0)
                    ->helperText('Requerido solo para Compra GND (costo asignado directo).'),
            ]),
            TextInput::make('motivo')->maxLength(255)->columnSpanFull(),
            Toggle::make('afecta_cmp')->label('Afecta CMP (auto)')->disabled()->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono'),
                TextColumn::make('tipo')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'Donacion' => 'Donación',
                        'CompraGND' => 'Compra GND',
                        'PerdidaDeterioro' => 'Pérdida/Deterioro',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Donacion' => 'info',
                        'CompraGND' => 'warning',
                        'PerdidaDeterioro' => 'danger',
                    }),
                TextColumn::make('articulo.titulo_nombre')->label('Artículo')->searchable(),
                TextColumn::make('cantidad')->numeric()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('costo_unitario')->label('Costo unit.')->money('Bs'),
                TextColumn::make('motivo')->limit(40)->toggleable(),
                TextColumn::make('usuario.name')->label('Registró')->toggleable(),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAjustesInventario::route('/'),
            'create' => Pages\CreateAjustesInventario::route('/create'),
        ];
    }
}
