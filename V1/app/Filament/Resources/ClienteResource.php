<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Recurso Filament de clientes (PRD Módulo 2) con control de crédito.
 */
class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Ventas y Caja';

    protected static ?string $modelLabel = 'Cliente';

    /**
     * Formulario de cliente con datos fiscales y límite de crédito.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nombre_razon_social')
                ->label('Nombre / Razón Social')
                ->required()
                ->maxLength(255),
            Grid::make(2)->schema([
                TextInput::make('nit_ci')
                    ->label('NIT / CI')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                TextInput::make('telefono')->tel()->maxLength(50),
            ]),
            Grid::make(2)->schema([
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('limite_credito')
                    ->label('Límite de crédito')
                    ->numeric()
                    ->prefix('Bs')
                    ->default(0),
            ]),
        ]);
    }

    /**
     * Tabla de clientes con saldo de créditos.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre_razon_social')->label('Nombre / Razón Social')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('nit_ci')->label('NIT / CI')->searchable(),
                TextColumn::make('email')->toggleable(),
                TextColumn::make('telefono')->toggleable(),
                TextColumn::make('limite_credito')->label('Límite crédito')->money('Bs'),
                TextColumn::make('ventas_count')->counts('ventas')->label('Ventas')->badge(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientes::route('/'),
            'create' => Pages\CreateClientes::route('/create'),
            'edit' => Pages\EditClientes::route('/{record}/edit'),
        ];
    }
}
