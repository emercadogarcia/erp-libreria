<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Venta web o de mostrador (PRD Módulos 1 y 2).
 * Las ventas web nacen en estado PendienteValidacion con comprobante adjunto;
 * el administrador las aprueba desde Filament y se genera el asiento contable.
 *
 * @property int $id
 * @property string $numero_recibo
 * @property ?int $cliente_id
 * @property ?string $cliente_nombre
 * @property ?string $cliente_nit_ci
 * @property string $origen
 * @property string $estado
 * @property string $forma_pago
 * @property ?string $comprobante_path
 * @property float $subtotal
 * @property float $descuento
 * @property float $total
 * @property bool $es_credito
 */
class Venta extends Model
{
    protected $table = 'ventas';

    public const ESTADOS = ['PendienteValidacion', 'Aprobada', 'Rechazada', 'Anulada'];

    protected $fillable = [
        'numero_recibo',
        'cliente_id',
        'cliente_nombre',
        'cliente_nit_ci',
        'cliente_email',
        'origen',
        'estado',
        'forma_pago',
        'comprobante_path',
        'subtotal',
        'descuento',
        'total',
        'es_credito',
        'aprobada_at',
        'aprobada_por',
    ];

    /**
     * Casting de variables financieras y de auditoría.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            'es_credito' => 'boolean',
            'aprobada_at' => 'datetime',
        ];
    }

    /**
     * Cliente registrado (null en ventas web de usuarios anónimos).
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Líneas de detalle de la venta.
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    /**
     * Usuario que aprobó la venta (ventas web).
     */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }

    /**
     * Pagos de crédito registrados contra esta venta.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(PagoCliente::class);
    }

    /**
     * Factura electrónica simulada asociada (si ya fue emitida).
     */
    public function factura(): HasMany
    {
        return $this->hasMany(Factura::class);
    }

    /**
     * Saldo pendiente de cobro (solo ventas a crédito).
     */
    public function getSaldoCreditoAttribute(): float
    {
        if (! $this->es_credito) {
            return 0.0;
        }

        return round($this->total - (float) $this->pagos()->sum('monto'), 2);
    }
}
