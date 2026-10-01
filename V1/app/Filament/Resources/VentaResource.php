<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ventaResource\Pages;
use App\Models\Venta;
use App\Services\InvoiceService;
use App\Services\SalesService;
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
use Illuminate\Support\HtmlString;

/**
 * Recurso Filament de ventas (PRD Módulos 1 y 2).
 *
 * - Ventas web en estado PendienteValidacion: aprobación/rechazo manual tras
 *   la revisión visual del comprobante cargado por el cliente.
 * - Ventas de mostrador (POS): consolidadas al instante por SalesService.
 * - Acción de facturación simulada (Recibo Informativo de Venta).
 * - Cobranza de créditos con historial de pagos por cliente.
 */
class VentaResource extends Resource
{
    protected static ?string $model = Venta::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Ventas y Caja';

    protected static ?string $modelLabel = 'Venta';

    protected static ?string $pluralModelLabel = 'Ventas';

    /**
     * Formulario del POS de mostrador (venta directa, sin lectores de barras:
     * búsqueda por texto estructurado o ID del ítem).
     */
    public static function form(Form $form): Form
    {
        return $form->schema(self::posFormSchema());
    }

    /**
     * Esquema compartido entre el recurso y la acción modal del POS.
     */
    public static function posFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('cliente_id')
                    ->label('Cliente (opcional)')
                    ->relationship('cliente', 'nombre_razon_social')
                    ->searchable()
                    ->preload()
                    ->helperText('Requerido para ventas a crédito'),
                Select::make('forma_pago')
                    ->label('Forma de pago')
                    ->options(['Efectivo' => 'Efectivo', 'QR' => 'QR', 'Credito' => 'A Crédito'])
                    ->default('Efectivo')
                    ->live()
                    ->required(),
            ]),
            Toggle::make('es_credito')
                ->label('Venta a crédito')
                ->live()
                ->helperText('Las ventas a crédito se registran en Cuentas por Cobrar y se cobran por cuotas.'),
            Repeater::make('items')
                ->label('Ítems de la venta')
                ->schema([
                    Select::make('articulo_id')
                        ->label('Artículo (búsqueda por título, autor o editorial)')
                        ->options(fn () => \App\Models\Articulo::query()
                            ->where('activo', true)
                            ->orderBy('titulo_nombre')
                            ->get()
                            ->mapWithKeys(fn ($a) => [$a->id => "[{$a->codigo_sistema}] {$a->titulo_nombre} (stock: {$a->stock_actual})"]))
                        ->searchable()
                        ->required()
                        ->distinct()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                    TextInput::make('cantidad')->numeric()->minValue(1)->default(1)->required(),
                ])
                ->columns(2)
                ->defaultItems(1)
                ->required(),
            Grid::make(2)->schema([
                TextInput::make('cliente_datos.nombre')
                    ->label('Nombre / Razón Social (cliente ocasional)')
                    ->maxLength(255),
                TextInput::make('cliente_datos.nit_ci')
                    ->label('NIT / CI (cliente ocasional)')
                    ->maxLength(20),
            ]),
        ];
    }

    /**
     * Tabla de ventas con badges de estado y acciones de flujo.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero_recibo')->label('Recibo')->searchable()->sortable()->fontFamily('mono'),
                TextColumn::make('origen')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'web' ? 'Web' : 'Mostrador')
                    ->color(fn (string $state) => $state === 'web' ? 'info' : 'gray'),
                TextColumn::make('cliente_nombre')->label('Cliente')->searchable()->default('—'),
                TextColumn::make('cliente_nit_ci')->label('NIT/CI')->toggleable(),
                TextColumn::make('estado')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'PendienteValidacion' => 'Pendiente de Validación',
                        'Aprobada' => 'Aprobada',
                        'Rechazada' => 'Rechazada',
                        'Anulada' => 'Anulada',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'PendienteValidacion' => 'warning',
                        'Aprobada' => 'success',
                        'Rechazada' => 'danger',
                        'Anulada' => 'gray',
                    }),
                TextColumn::make('forma_pago')->label('Pago')->badge(),
                TextColumn::make('total')
                    ->money('Bs')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('es_credito')->label('Crédito')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'A Crédito' : 'Contado')
                    ->color(fn (bool $state) => $state ? 'warning' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('estado')->options([
                    'PendienteValidacion' => 'Pendiente de Validación',
                    'Aprobada' => 'Aprobada',
                    'Rechazada' => 'Rechazada',
                    'Anulada' => 'Anulada',
                ]),
                SelectFilter::make('origen')->options(['web' => 'Web', 'mostrador' => 'Mostrador']),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()->visible(fn (Venta $record) => $record->estado === 'PendienteValidacion'),
                // Aprobación manual tras revisión visual del comprobante (PRD Módulo 2)
                Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Venta $record) => $record->estado === 'PendienteValidacion')
                    ->requiresConfirmation()
                    ->modalDescription('Confirma haber revisado visualmente el comprobante de pago. Se descontará stock y se generará el asiento contable.')
                    ->action(function (Venta $record) {
                        SalesService::aprobarVenta($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Venta aprobada y contabilizada')
                            ->success()
                            ->send();
                    }),
                Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Venta $record) => $record->estado === 'PendienteValidacion')
                    ->requiresConfirmation()
                    ->modalDescription('La venta quedará rechazada sin afectar stock ni contabilidad.')
                    ->action(function (Venta $record) {
                        SalesService::rechazarVenta($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Venta rechazada')
                            ->send();
                    }),
                // Cobro de créditos/cuotas (PRD Módulo 2: control de créditos y cobranzas)
                Action::make('cobrarCredito')
                    ->label('Cobrar crédito')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->visible(fn (Venta $record) => $record->es_credito && $record->estado === 'Aprobada' && $record->saldo_credito > 0)
                    ->form([
                        \Filament\Forms\Components\Placeholder::make('saldo')
                            ->label('Saldo pendiente')
                            ->content(fn (Venta $record) => 'Bs '.number_format($record->saldo_credito, 2)),
                        Select::make('metodo')->options(['Efectivo' => 'Efectivo', 'QR' => 'QR'])->default('Efectivo')->required(),
                        TextInput::make('monto')->numeric()->prefix('Bs')->required()
                            ->maxValue(fn (Venta $record) => $record->saldo_credito),
                        TextInput::make('observacion')->maxLength(255),
                    ])
                    ->action(function (Venta $record, array $data) {
                        SalesService::registrarPagoCredito($record, (float) $data['monto'], $data['metodo'], $data['observacion'] ?? '');

                        \Filament\Notifications\Notification::make()
                            ->title('Pago registrado y contabilizado')
                            ->success()
                            ->send();
                    }),
                // Emisión del Recibo Informativo (facturación simulada, PRD Módulo 6)
                Action::make('facturar')
                    ->label('Emitir Recibo')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (Venta $record) => $record->estado === 'Aprobada' && ! $record->factura()->exists())
                    ->requiresConfirmation()
                    ->modalDescription('Se generará el Recibo Informativo de Venta con datos fiscales simulados (CUFD/CUIS/Código de Control).')
                    ->action(function (Venta $record) {
                        $factura = InvoiceService::emitirFactura($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Recibo emitido: '.$factura->numero_factura)
                            ->success()
                            ->send();
                    }),
                Action::make('verRecibo')
                    ->label('Recibo')
                    ->icon('heroicon-o-printer')
                    ->visible(fn (Venta $record) => $record->factura()->exists())
                    ->url(fn (Venta $record) => route('recibo.print', $record), shouldOpenInNewTab: true),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Páginas del recurso.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVentas::route('/'),
            'create' => Pages\CreateVentas::route('/create'),
            'view' => Pages\ViewVentas::route('/{record}'),
        ];
    }
}
