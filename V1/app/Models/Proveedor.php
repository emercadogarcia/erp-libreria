<?php

namespace App\Models;

use Database\Factories\ProveedorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proveedor nacional o internacional de libros y material de escritorio.
 *
 * @property int $id
 * @property string $nombre_razon_social
 * @property string $pais
 */
class Proveedor extends Model
{
    /** @use HasFactory<ProveedorFactory> */
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = ['nombre_razon_social', 'nit', 'pais', 'contacto'];

    /**
     * Compras registradas al proveedor.
     */
    public function compras(): HasMany
    {
        return $this->hasMany(CompraCabecera::class);
    }
}
