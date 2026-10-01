<?php

use App\Models\Venta;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Módulo 1: Catálogo público con carrito y checkout (Livewire)
Route::get('/', \App\Livewire\Catalogo::class)->name('catalogo');

// Módulo 6: Recibo Informativo de Venta imprimible (formato carta/tique)
Route::get('/recibo/{venta}/imprimir', function (Venta $venta) {
    return view('recibos.recibo-informativo', ['venta' => $venta->load(['detalles.articulo', 'cliente'])]);
})->name('recibo.print');

Route::post('/logout', function () {
    auth()->logout();

    return redirect('/');
})->name('logout');
