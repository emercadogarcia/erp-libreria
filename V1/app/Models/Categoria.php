<?php

namespace App\Models;

use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoría de productos de la librería (Libros, Escritorio, etc.).
 *
 * @property int $id
 * @property string $nombre
 */
class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    protected $table = 'categorias';

    protected $fillable = ['nombre', 'descripcion'];

    /**
     * Artículos pertenecientes a esta categoría.
     */
    public function articulos(): HasMany
    {
        return $this->hasMany(Articulo::class);
    }
}
