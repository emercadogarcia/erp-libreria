<div>
    {{-- ================= CABECERA INTERACTIVA ================= --}}
    <header class="no-print sticky top-0 z-40 border-b border-stone-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2">
                <svg class="h-8 w-8 text-brand-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                </svg>
                <div>
                    <div class="text-sm font-bold text-stone-800">Librería El Ateneo</div>
                    <div class="text-[10px] uppercase tracking-wider text-stone-400">La Paz - Bolivia</div>
                </div>
            </a>
            <button type="button" wire:click="$toggle('checkoutAbierto')" @if($this->conteoCarrito === 0) disabled @endif
                    class="relative rounded-full bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700 disabled:opacity-40">
                🛒 Carrito
                @if($this->conteoCarrito > 0)
                    <span class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-stone-900 text-[10px] font-bold text-white">{{ $this->conteoCarrito }}</span>
                @endif
            </button>
        </div>
    </header>

    {{-- Toast Livewire --}}
    <div x-data="{ show: false, msg: '' }"
         @toast.window="msg = $event.detail.mensaje; show = true; setTimeout(() => show = false, 3000)"
         x-cloak
         class="no-print fixed bottom-6 left-1/2 z-[60] -translate-x-1/2">
        <div x-show="show" x-transition
             class="rounded-full bg-stone-900 px-5 py-2 text-sm text-white shadow-lg"
             x-text="msg"></div>
    </div>

    {{-- Portada minimalista --}}
    <section class="border-b border-stone-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-12 text-center">
            <h1 class="text-3xl font-bold text-stone-800 sm:text-4xl">Libros y Material de Escritorio</h1>
            <p class="mx-auto mt-2 max-w-xl text-stone-500">
                Catálogo de {{ config('accounting.empresa.nombre') }}. Pagá con QR y subí tu comprobante — validamos tu pedido el mismo día.
            </p>

            {{-- Búsqueda por texto estructurado (Título / Autor / Editorial) --}}
            <div class="mx-auto mt-6 max-w-md">
                <input type="search" wire:model.live.debounce.400ms="busqueda"
                       placeholder="Buscar por título, autor o editorial…"
                       class="w-full rounded-full border border-stone-300 px-5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
    </section>

    <main class="mx-auto max-w-6xl px-4 py-8">
        {{-- Filtro de categorías --}}
        <nav class="no-print mb-6 flex flex-wrap justify-center gap-2">
            <button type="button" wire:click="$set('categoriaId', null)"
                    class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $categoriaId === null ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 hover:bg-stone-100' }}">
                Todos
            </button>
            @foreach ($this->categorias as $cat)
                <button type="button" wire:click="$set('categoriaId', {{ $cat->id }})"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition {{ $categoriaId === $cat->id ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 hover:bg-stone-100' }}">
                    {{ $cat->nombre }} ({{ $cat->articulos_count }})
                </button>
            @endforeach
        </nav>

        {{-- Grilla de productos --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($this->articulos as $articulo)
                <article class="libro-card flex flex-col rounded-xl border border-stone-200 bg-white p-4 shadow-sm transition hover:shadow-md">
                    {{-- Portada de libro estilizada (CSS puro, sin imágenes) --}}
                    <div class="libro-cover mx-auto mb-3 flex h-44 w-32 items-center justify-center rounded-r-lg rounded-l-sm bg-gradient-to-br from-brand-500 to-brand-700 p-3 text-center shadow-md transition-transform">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wide text-white/80">{{ Str::limit($articulo->titulo_nombre, 18) }}</div>
                            <div class="mt-1 text-[9px] text-white/70">{{ Str::limit($articulo->autor ?? $articulo->editorial ?? '', 22) }}</div>
                        </div>
                    </div>

                    <h2 class="text-sm font-semibold text-stone-800">{{ $articulo->titulo_nombre }}</h2>
                    <p class="mt-0.5 text-xs text-stone-500">
                        {{ $articulo->autor ?? $articulo->editorial ?? 'Material de escritorio' }}
                    </p>

                    <div class="mt-auto flex items-center justify-between pt-3">
                        <div>
                            <div class="text-lg font-bold text-stone-900">Bs {{ number_format($articulo->precio_venta, 2) }}</div>
                            <div class="text-[11px] {{ $articulo->stock_actual <= $articulo->stock_minimo ? 'text-red-500' : 'text-stone-400' }}">
                                Stock: {{ $articulo->stock_actual }}
            </div>
                        </div>
                        <button type="button" wire:click="agregar({{ $articulo->id }})"
                                class="no-print rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700">
                            + Añadir
                        </button>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-xl bg-white p-10 text-center text-stone-500">
                    No encontramos libros con ese criterio de búsqueda.
                </div>
            @endforelse
        </div>
    </main>

    {{-- Footer legal --}}
    <footer class="border-t border-stone-200 bg-white py-6 text-center text-xs text-stone-400">
        {{ config('accounting.empresa.nombre') }} · NIT {{ config('accounting.empresa.nit') }} · {{ config('accounting.empresa.direccion') }}
        <br>
        <span class="italic">«La Ley N° 453 obliga a los proveedores a exhibir su certificado de habilitación y entregar facturas en todos sus sistemas de cobro.»</span>
    </footer>

    {{-- ================= MODAL DE CHECKOUT (2 pasos) ================= --}}
    <div class="no-print fixed inset-0 z-50 {{ $checkoutAbierto ? '' : 'hidden' }}">
        <div class="absolute inset-0 bg-stone-900/50" wire:click="$set('checkoutAbierto', false)"></div>

        <div class="absolute left-1/2 top-1/2 max-h-[90vh] w-full max-w-lg -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-xl bg-white p-6 shadow-2xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-stone-800">{{ $paso === 1 ? 'Tus datos' : 'Pago con QR' }}</h3>
                <button type="button" wire:click="$set('checkoutAbierto', false)" class="text-stone-400 hover:text-stone-600">✕</button>
            </div>

            @if ($paso === 1)
                {{-- Paso 1: datos del cliente (checkout simplificado) --}}
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-medium text-stone-600">Nombre / Razón Social</label>
                        <input type="text" wire:model="nombre" class="mt-1 w-full rounded-lg border border-stone-300 text-sm">
                        @error('nombre') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-stone-600">NIT / CI</label>
                        <input type="text" wire:model="nit_ci" class="mt-1 w-full rounded-lg border border-stone-300 text-sm">
                        @error('nit_ci') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-stone-600">Correo Electrónico</label>
                        <input type="email" wire:model="email" class="mt-1 w-full rounded-lg border border-stone-300 text-sm">
                        @error('email') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    {{-- Resumen del pedido --}}
                    <div class="rounded-lg bg-stone-50 p-3 text-sm">
                        @foreach ($this->itemsCarrito as $item)
                            <div class="flex justify-between text-stone-600">
                                <span>{{ $item['cantidad'] }}× {{ Str::limit($item['titulo'], 28) }}</span>
                                <span>Bs {{ number_format($item['subtotal'], 2) }}</span>
                            </div>
                        @endforeach
                        <div class="mt-1 flex justify-between border-t border-stone-200 pt-1 font-bold text-stone-900">
                            <span>Total</span><span>Bs {{ number_format($this->totalCarrito, 2) }}</span>
                        </div>
                    </div>

                    <button type="button" wire:click="continuarPago" class="w-full rounded-lg bg-brand-600 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                        Continuar al pago →
                    </button>
                </div>

            @elseif ($paso === 2)
                {{-- Paso 2: QR estático + comprobante --}}
                <div class="space-y-4">
                    <div class="text-center">
                        <div class="mx-auto w-fit rounded-lg border border-stone-200 bg-white p-3 shadow-sm">
                            {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(160)
                                ->backgroundColor(255, 255, 255)
                                ->format('svg')
                                ->generate(json_encode($this->instruccionesPago)) !!}
                        </div>
                        <p class="mt-2 text-xs text-stone-500">Escaneá con tu app bancaria</p>
                    </div>

                    <div class="rounded-lg bg-brand-50 p-3 text-xs text-brand-900">
                        <div class="font-semibold">Instrucciones de pago</div>
                        <div>1. Transferí <strong>Bs {{ number_format($this->totalCarrito, 2) }}</strong> al QR o a: {{ $this->instruccionesPago['banco'] }}</div>
                        <div>2. Tomá captura del comprobante</div>
                        <div>3. Adjuntá la captura (JPG/PNG) aquí abajo</div>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-stone-600">Comprobante de transferencia (JPG/PNG)</label>
                        <input type="file" wire:model="comprobante" accept="image/jpeg,image/png"
                               class="mt-1 w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-3 file:py-1.5 file:text-white">
                        @error('comprobante') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('paso', 1)" class="rounded-lg border px-4 py-2.5 text-sm">← Volver</button>
                        <button type="button" wire:click="confirmarPedido"
                                class="flex-1 rounded-lg bg-brand-600 py-2.5 text-sm font-semibold text-white hover:bg-brand-700"
                                wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="confirmarPedido">Confirmar pedido</span>
                            <span wire:loading wire:target="confirmarPedido">Subiendo…</span>
                        </button>
                    </div>
                    <div wire:loading wire:target="comprobante" class="text-center text-xs text-stone-500">Subiendo comprobante…</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Confirmación final: recibo preliminar imprimible --}}
    @if ($ventaId && $paso === 3)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-stone-100 p-4">
            @php $venta = \App\Models\Venta::find($ventaId); @endphp
            @if ($venta)
                <div class="no-print mx-auto mt-4 max-w-md space-y-2">
                    <div class="rounded-lg bg-white p-4 text-center shadow">
                        <div class="text-2xl">✅</div>
                        <div class="font-bold text-stone-800">¡Pedido confirmado!</div>
                        <div class="text-sm text-stone-500">Recibo N° {{ $venta->numero_recibo }} — Pendiente de Validación</div>
                    </div>
                    <button type="button" onclick="window.print()" class="w-full rounded-lg bg-stone-900 py-2.5 text-sm font-semibold text-white">🖨 Imprimir recibo</button>
                    <button type="button" wire:click="$set('paso', 1); $set('ventaId', null)" class="w-full rounded-lg border bg-white py-2.5 text-sm">Volver al catálogo</button>
                </div>
                <div class="mx-auto mt-4 max-w-2xl">
                    @include('recibos.recibo-informativo', ['venta' => $venta])
                </div>
            @endif
        </div>
    @endif
</div>
