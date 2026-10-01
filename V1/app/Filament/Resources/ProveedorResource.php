<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProveedorResource\Pages;
use App\Models\Proveedor;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Recurso Filament de proveedores (PRD Módulo 3), nacionales e internacionales.
 */
class ProveedorResource extends Resource
{
    protected static ?string $model = Proveedor::class;

    protected static ?string $slug = 'proveedores';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Compras';

    protected static ?string $modelLabel = 'Proveedor';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nombre_razon_social')
                ->label('Nombre / Razón Social')
                ->required()
                ->maxLength(255),
            Grid::make(3)->schema([
                TextInput::make('nit')->maxLength(20),
                TextInput::make('pais')
                    ->label('País (Nacional/Internacional)')
                    ->default('Bolivia')
                    ->required()
                    ->maxLength(80),
                TextInput::make('contacto')->maxLength(255),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre_razon_social')->label('Proveedor')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('nit')->searchable(),
                TextColumn::make('pais')->badge()
                    ->color(fn (string $state) => $state === 'Bolivia' ? 'success' : 'info'),
                TextColumn::make('contacto')->toggleable(),
                TextColumn::make('compras_count')->counts('compras')->label('Compras')->badge(),
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
            'index' => Pages\ListProveedores::route('/'),
            'create' => Pages\CreateProveedores::route('/create'),
            'edit' => Pages\EditProveedores::route('/{record}/edit'),
        ];
    }
}
