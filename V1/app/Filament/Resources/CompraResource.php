<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompraResource\Pages;
use App\Models\CompraCabecera;
use App\Services\PurchaseService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
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
 * Recurso Filament del flujo secuencial e inalterable de compras (PRD Módulo 3):
 *
 * 1. Cotización -> 2. Oferta -> 3. Orden de Compra (P.C.) -> 4. Recepción de
 * Mercadería / Nota de Ingreso (E.M.) -> 5. Factura de Proveedor (F.fob) ->
 * 6. Costos Adicionales / Prorrateo + 7. Gasto de Importación (I.P.) -> 8. Pago a Proveedor.
 */
class CompraResource extends Resource
{
    protected static ?string $model = CompraCabecera::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Compras';

    protected static ?string $modelLabel = 'Compra';

    protected static ?string $pluralModelLabel = 'Compras';

    /**
     * Formulario de creación: cabecera + líneas con costo FOB unitario.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(3)->schema([
                Select::make('proveedor_id')
                    ->label('Proveedor')
                    ->relationship('proveedor', 'nombre_razon_social')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('tipo_compra')
                    ->label('Tipo de compra')
                    ->options(['Nacional' => 'Nacional', 'Internacional' => 'Internacional'])
                    ->default('Nacional')
                    ->required(),
                DatePicker::make('fecha_emision')->label('Fecha de emisión')->default(now())->required(),
            ]),
            Grid::make(2)->schema([
                DatePicker::make('fecha_estimada_recepcion')->label('Fecha estimada de recepción'),
                Toggle::make('es_gnd')
                    ->label('Gasto No Deducible (GND)')
                    ->helperText('Compra sin factura fiscal: contabiliza el 100% como GND interno, sin crédito fiscal.'),
            ]),
            TextInput::make('glosa')->label('Glosa / Observación')->maxLength(255)->columnSpanFull(),
            Repeater::make('detalles')
                ->label('Detalle de la compra')
                ->relationship()
                ->schema([
                    Select::make('articulo_id')
                        ->label('Artículo')
                        ->relationship('articulo', 'titulo_nombre')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->distinct()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                    TextInput::make('cantidad')->numeric()->minValue(1)->default(1)->required(),
                    TextInput::make('costo_unitario_fob')
                        ->label('Costo unitario (FOB)')
                        ->numeric()
                        ->prefix('Bs')
                        ->default(0)
                        ->required(),
                ])
                ->columns(3)
                ->defaultItems(1)
                ->required()
                ->columnSpanFull(),
        ]);
    }

    /**
     * Tabla con el estado del flujo secuencial y acciones por etapa.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')->label('Código')->searchable()->fontFamily('mono'),
                TextColumn::make('proveedor.nombre_razon_social')->label('Proveedor')->searchable(),
                TextColumn::make('tipo_compra')->label('Tipo')->badge()
                    ->color(fn (string $state) => $state === 'Internacional' ? 'info' : 'success'),
                TextColumn::make('estado_flujo')->label('Etapa del flujo')->badge()
                    ->formatStateUsing(fn (string $state) => self::etiquetaEstado($state))
                    ->color(fn (string $state) => match ($state) {
                        'Cotizacion' => 'gray',
                        'Oferta' => 'info',
                        'OrdenCompra' => 'primary',
                        'NotaIngreso' => 'warning',
                        'Facturado' => 'warning',
                        'GastoImportacion' => 'danger',
                        'Pagado' => 'success',
                    }),
                TextColumn::make('valor_fob')->label('F.fob')->money('Bs')->toggleable(),
                TextColumn::make('costos_adicionales_prorrateo')->label('Prorrateo')->money('Bs')->toggleable(),
                TextColumn::make('total_final')->label('Total final')->money('Bs')->weight('bold'),
                TextColumn::make('es_gnd')->label('GND')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'GND' : 'Fiscal')
                    ->color(fn (bool $state) => $state ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('estado_flujo')->label('Etapa')->options(
                    collect(CompraCabecera::ESTADOS)->mapWithKeys(fn ($e) => [$e => self::etiquetaEstado($e)])->all()
                ),
                SelectFilter::make('tipo_compra')->options(['Nacional' => 'Nacional', 'Internacional' => 'Internacional']),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()->visible(fn (CompraCabecera $record) => in_array($record->estado_flujo, ['Cotizacion', 'Oferta'], true)),

                // ---- 2. Oferta ----
                Action::make('ofertar')
                    ->label('A Oferta')
                    ->visible(fn (CompraCabecera $record) => $record->estado_flujo === 'Cotizacion')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->requiresConfirmation()
                    ->action(function (CompraCabecera $record) {
                        PurchaseService::avanzarFlujo($record);
                        \Filament\Notifications\Notification::make()->title('Compra en etapa Oferta')->success()->send();
                    }),

                // ---- 3. Orden de Compra (P.C.) ----
                Action::make('ordenCompra')
                    ->label('Emitir O. de Compra')
                    ->visible(fn (CompraCabecera $record) => $record->estado_flujo === 'Oferta')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->requiresConfirmation()
                    ->action(function (CompraCabecera $record) {
                        PurchaseService::avanzarFlujo($record);
                        \Filament\Notifications\Notification::make()->title('Orden de Compra emitida')->success()->send();
                    }),

                // ---- 4. Recepción de Mercadería / Nota de Ingreso (E.M.) ----
                Action::make('notaIngreso')
                    ->label('Registrar Nota de Ingreso')
                    ->visible(fn (CompraCabecera $record) => $record->estado_flujo === 'OrdenCompra')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Ingresa la mercadería al inventario, recalcula el CMP/P.V. y genera el asiento de compra (partida doble).')
                    ->action(function (CompraCabecera $record) {
                        PurchaseService::registrarIngresoMercaderia($record);
                        \Filament\Notifications\Notification::make()->title('Mercadería ingresada y contabilizada')->success()->send();
                    }),

                // ---- 5. Factura de Proveedor (F.fob) ----
                Action::make('facturaFob')
                    ->label('Registrar Factura F.fob')
                    ->visible(fn (CompraCabecera $record) => $record->estado_flujo === 'NotaIngreso')
                    ->icon('heroicon-o-document-currency-dollar')
                    ->form([
                        TextInput::make('valor_fob')
                            ->label('Valor FOB de la factura del proveedor')
                            ->numeric()
                            ->prefix('Bs')
                            ->default(fn (CompraCabecera $record) => $record->total_fob)
                            ->required(),
                    ])
                    ->action(function (CompraCabecera $record, array $data) {
                        PurchaseService::registrarFacturaFob($record, (float) $data['valor_fob']);
                        PurchaseService::avanzarFlujo($record); // NotaIngreso -> Facturado
                        \Filament\Notifications\Notification::make()->title('Factura FOB registrada')->success()->send();
                    }),

                // ---- 6. Costos Adicionales / Prorrateo + 7. Gasto de Importación (I.P.) ----
                Action::make('prorrateo')
                    ->label('Prorrateo de Costos')
                    ->visible(fn (CompraCabecera $record) => $record->estado_flujo === 'Facturado')
                    ->icon('heroicon-o-calculator')
                    ->color('danger')
                    ->form([
                        TextInput::make('costos_adicionales')
                            ->label('Costos adicionales (aranceles + fletes + estiba)')
                            ->numeric()
                            ->prefix('Bs')
                            ->default(0)
                            ->required()
                            ->helperText('Se prorratea proporcionalmente al valor FOB de cada línea y se recalcula el CMP y el P.V. de cada artículo.'),
                    ])
                    ->action(function (CompraCabecera $record, array $data) {
                        PurchaseService::aplicarProrrateo($record, (float) $data['costos_adicionales']);
                        PurchaseService::avanzarFlujo($record); // Facturado -> GastoImportacion
                        \Filament\Notifications\Notification::make()
                            ->title('Prorrateo aplicado: costos unitarios y P.V. recalculados')
                            ->success()
                            ->send();
                    }),

                // ---- 8. Pago a Proveedor ----
                Action::make('pagar')
                    ->label('Pagar a Proveedor')
                    ->visible(fn (CompraCabecera $record) => in_array($record->estado_flujo, ['GastoImportacion', 'Facturado'], true))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Genera el asiento de pago (DEBE Proveedores | HABER Banco) y cierra el flujo.')
                    ->action(function (CompraCabecera $record) {
                        PurchaseService::registrarPagoProveedor($record);
                        \Filament\Notifications\Notification::make()->title('Compra pagada (flujo completado)')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Etiquetas legibles de las etapas del flujo.
     */
    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'Cotizacion' => '1. Cotización',
            'Oferta' => '2. Oferta',
            'OrdenCompra' => '3. Orden de Compra (P.C.)',
            'NotaIngreso' => '4. Nota de Ingreso (E.M.)',
            'Facturado' => '5. Facturado (F.fob)',
            'GastoImportacion' => '6-7. Prorrateo / Gasto I.P.',
            'Pagado' => '8. Pagado',
            default => $estado,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompras::route('/'),
            'create' => Pages\CreateCompras::route('/create'),
            'view' => Pages\ViewCompras::route('/{record}'),
        ];
    }
}
