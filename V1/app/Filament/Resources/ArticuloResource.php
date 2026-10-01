<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticuloResource\Pages;
use App\Models\Articulo;
use App\Services\InventoryService;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Recurso Filament de artículos (PRD Módulo 4): ficha técnica simplificada
 * con Título/Nombre, Autor, Editorial, Categoría, Stock actual, Stock mínimo,
 * Costo Unitario (CMP) y Precio de Venta.
 */
class ArticuloResource extends Resource
{
    protected static ?string $model = Articulo::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $modelLabel = 'Artículo';

    protected static ?string $pluralModelLabel = 'Artículos';

    /**
     * Ficha técnica simplificada del artículo.
     */
    public static function form(Form $form): Form
    {
        return $form->schema(self::camposFicha());
    }

    /**
     * Campos compartidos entre el formulario y el modal de kardex.
     */
    public static function camposFicha(): array
    {
        return [
            TextInput::make('codigo_sistema')
                ->label('Código de sistema')
                ->default(fn () => 'ART-'.str_pad((string) ((int) Articulo::max('id') + 1), 5, '0', STR_PAD_LEFT))
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('titulo_nombre')
                ->label('Título / Nombre')
                ->required()
                ->maxLength(255),
            Grid::make(2)->schema([
                TextInput::make('autor')->label('Autor')->maxLength(150),
                TextInput::make('editorial')->label('Editorial')->maxLength(150),
            ]),
            Grid::make(2)->schema([
                Select::make('categoria_id')
                    ->label('Categoría')
                    ->relationship('categoria', 'nombre')
                    ->searchable()
                    ->preload(),
                Select::make('tipo')
                    ->options(['libro' => 'Libro', 'escritorio' => 'Material de Escritorio'])
                    ->default('libro')
                    ->required(),
            ]),
            Grid::make(3)->schema([
                TextInput::make('stock_actual')->label('Stock actual')->numeric()->default(0)->required(),
                TextInput::make('stock_minimo')->label('Stock mínimo')->numeric()->default(5)->required(),
                Toggle::make('es_gnd')
                    ->label('Gasto No Deducible (GND)')
                    ->helperText('Compras sin factura fiscal que afectan el inventario físico.'),
            ]),
            Grid::make(2)->schema([
                TextInput::make('costo_medio_ponderado')
                    ->label('Costo Medio Ponderado (CMP)')
                    ->numeric()
                    ->prefix('Bs')
                    ->default(0),
                TextInput::make('precio_venta')
                    ->label('Precio de Venta (P.V.)')
                    ->numeric()
                    ->prefix('Bs')
                    ->default(0)
                    ->hint('Se recalcula automáticamente con el margen parametrizado'),
            ]),
        ];
    }

    /**
     * Tabla del listado con alertas de quiebre de stock.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo_sistema')->label('Código')->searchable()->sortable(),
                TextColumn::make('titulo_nombre')->label('Título / Nombre')->searchable()->sortable()->weight('semibold'),
                TextColumn::make('autor')->searchable()->toggleable(),
                TextColumn::make('editorial')->searchable()->toggleable(),
                TextColumn::make('categoria.nombre')->label('Categoría')->badge(),
                TextColumn::make('stock_actual')
                    ->label('Stock')
                    ->numeric()
                    ->sortable()
                    ->color(fn (Articulo $record) => $record->stock_actual <= $record->stock_minimo ? 'danger' : 'success'),
                TextColumn::make('costo_medio_ponderado')
                    ->label('CMP')
                    ->formatStateUsing(fn ($state) => 'Bs '.number_format((float) $state, 2))
                    ->sortable(),
                TextColumn::make('precio_venta')
                    ->label('P.V.')
                    ->formatStateUsing(fn ($state) => 'Bs '.number_format((float) $state, 2))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo')->options(['libro' => 'Libro', 'escritorio' => 'Escritorio']),
                SelectFilter::make('categoria')->relationship('categoria', 'nombre'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('kardex')
                    ->label('Kardex')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->form([
                        Placeholder::make('kardex')
                            ->label('Movimientos del artículo (compras, ventas y ajustes)')
                            ->content(fn (Articulo $record) => view('filament.kardex-table', [
                                'movimientos' => InventoryService::kardex($record),
                            ])),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->bulkActions([])
            ->defaultSort('titulo_nombre');
    }

    /**
     * Páginas del recurso (listado + crear + editar).
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticulos::route('/'),
            'create' => Pages\CreateArticulo::route('/create'),
            'edit' => Pages\EditArticulo::route('/{record}/edit'),
        ];
    }
}
