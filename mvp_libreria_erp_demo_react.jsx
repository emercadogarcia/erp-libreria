export default function LibreriaERPMVP() {
  const productos = [
    {
      id: 1,
      titulo: 'Cien Años de Soledad',
      autor: 'Gabriel García Márquez',
      stock: 12,
      precio: 120,
      categoria: 'Libros',
    },
    {
      id: 2,
      titulo: 'Agenda Ejecutiva 2026',
      autor: 'Office Plus',
      stock: 30,
      precio: 45,
      categoria: 'Escritorio',
    },
    {
      id: 3,
      titulo: 'Clean Code',
      autor: 'Robert C. Martin',
      stock: 5,
      precio: 250,
      categoria: 'Tecnología',
    },
  ];

  const compras = [
    {
      proveedor: 'Importadora Andes',
      estado: 'NotaIngreso',
      total: 'Bs 12,500',
    },
    {
      proveedor: 'Distribuidora Escolar',
      estado: 'Pagado',
      total: 'Bs 4,800',
    },
  ];

  const ventas = [
    {
      cliente: 'Juan Pérez',
      estado: 'Pendiente de Validación',
      total: 'Bs 340',
    },
    {
      cliente: 'Empresa Delta SRL',
      estado: 'Aprobada',
      total: 'Bs 1,250',
    },
  ];

  const asientos = [
    {
      glosa: 'Venta Recibo #001',
      debe: 'Bs 340',
      haber: 'Bs 340',
    },
    {
      glosa: 'Compra GND Material Escritorio',
      debe: 'Bs 1,200',
      haber: 'Bs 1,200',
    },
  ];

  return (
    <div className="min-h-screen bg-gray-100 text-gray-900 p-6">
      <div className="max-w-7xl mx-auto space-y-8">
        <header className="bg-white rounded-3xl shadow-sm p-6 flex flex-col md:flex-row md:items-center md:justify-between">
          <div>
            <h1 className="text-4xl font-bold">ERP Librería Bolivia - MVP</h1>
            <p className="text-gray-500 mt-2">
              Laravel + Filament + PostgreSQL · Vista inicial funcional del sistema.
            </p>
          </div>

          <div className="grid grid-cols-2 gap-4 mt-6 md:mt-0">
            <div className="bg-blue-50 rounded-2xl p-4">
              <p className="text-sm text-gray-500">Ventas del Día</p>
              <h2 className="text-2xl font-bold">Bs 5,420</h2>
            </div>

            <div className="bg-green-50 rounded-2xl p-4">
              <p className="text-sm text-gray-500">Stock Total</p>
              <h2 className="text-2xl font-bold">1,245</h2>
            </div>
          </div>
        </header>

        <section className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 bg-white rounded-3xl shadow-sm p-6">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h2 className="text-2xl font-semibold">Catálogo Público</h2>
                <p className="text-gray-500 text-sm">
                  Simulación del e-commerce con carrito y checkout.
                </p>
              </div>

              <button className="bg-black text-white px-4 py-2 rounded-xl">
                Ver Carrito
              </button>
            </div>

            <div className="grid md:grid-cols-2 gap-4">
              {productos.map((producto) => (
                <div
                  key={producto.id}
                  className="border rounded-2xl p-4 hover:shadow-md transition"
                >
                  <div className="flex items-center justify-between">
                    <span className="text-xs bg-gray-100 px-3 py-1 rounded-full">
                      {producto.categoria}
                    </span>
                    <span className="text-sm text-gray-500">
                      Stock: {producto.stock}
                    </span>
                  </div>

                  <h3 className="text-xl font-semibold mt-4">
                    {producto.titulo}
                  </h3>

                  <p className="text-gray-500 text-sm mt-1">
                    {producto.autor}
                  </p>

                  <div className="flex items-center justify-between mt-6">
                    <span className="text-2xl font-bold">
                      Bs {producto.precio}
                    </span>

                    <button className="bg-blue-600 text-white px-4 py-2 rounded-xl">
                      Añadir
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="bg-white rounded-3xl shadow-sm p-6 space-y-5">
            <div>
              <h2 className="text-2xl font-semibold">Checkout</h2>
              <p className="text-gray-500 text-sm">
                Flujo simplificado de compra.
              </p>
            </div>

            <input
              className="w-full border rounded-xl px-4 py-3"
              placeholder="Nombre o Razón Social"
            />

            <input
              className="w-full border rounded-xl px-4 py-3"
              placeholder="NIT / CI"
            />

            <input
              className="w-full border rounded-xl px-4 py-3"
              placeholder="Correo electrónico"
            />

            <div className="border-2 border-dashed rounded-2xl p-6 text-center bg-gray-50">
              <div className="w-40 h-40 mx-auto bg-black rounded-2xl flex items-center justify-center text-white text-sm">
                QR Pago Banco
              </div>

              <p className="text-sm text-gray-500 mt-4">
                Subir comprobante JPG/PNG
              </p>

              <button className="mt-3 bg-gray-900 text-white px-4 py-2 rounded-xl">
                Adjuntar Archivo
              </button>
            </div>

            <button className="w-full bg-green-600 text-white py-3 rounded-2xl text-lg font-semibold">
              Confirmar Pedido
            </button>
          </div>
        </section>

        <section className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="bg-white rounded-3xl shadow-sm p-6">
            <div className="flex items-center justify-between mb-5">
              <div>
                <h2 className="text-2xl font-semibold">
                  Ventas y Validación Manual
                </h2>
                <p className="text-gray-500 text-sm">
                  Panel administrativo tipo Filament.
                </p>
              </div>

              <button className="bg-indigo-600 text-white px-4 py-2 rounded-xl">
                Abrir POS
              </button>
            </div>

            <div className="space-y-4">
              {ventas.map((venta, index) => (
                <div
                  key={index}
                  className="border rounded-2xl p-4 flex items-center justify-between"
                >
                  <div>
                    <h3 className="font-semibold">{venta.cliente}</h3>
                    <p className="text-sm text-gray-500">{venta.estado}</p>
                  </div>

                  <div className="text-right">
                    <p className="font-bold">{venta.total}</p>
                    <button className="mt-2 text-sm bg-black text-white px-3 py-1 rounded-lg">
                      Aprobar
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="bg-white rounded-3xl shadow-sm p-6">
            <div className="mb-5">
              <h2 className="text-2xl font-semibold">
                Flujo de Compras e Importaciones
              </h2>
              <p className="text-gray-500 text-sm">
                Secuencia definida en el PRD.
              </p>
            </div>

            <div className="flex flex-wrap gap-2 mb-5">
              {[
                'Cotización',
                'Oferta',
                'Orden Compra',
                'Nota Ingreso',
                'Factura FOB',
                'Prorrateo',
                'Pago',
              ].map((step) => (
                <span
                  key={step}
                  className="bg-blue-50 text-blue-700 px-3 py-2 rounded-full text-sm"
                >
                  {step}
                </span>
              ))}
            </div>

            <div className="space-y-4">
              {compras.map((compra, index) => (
                <div
                  key={index}
                  className="border rounded-2xl p-4 flex items-center justify-between"
                >
                  <div>
                    <h3 className="font-semibold">{compra.proveedor}</h3>
                    <p className="text-sm text-gray-500">
                      Estado: {compra.estado}
                    </p>
                  </div>

                  <span className="font-bold">{compra.total}</span>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="bg-white rounded-3xl shadow-sm p-6">
            <div className="mb-5">
              <h2 className="text-2xl font-semibold">
                Inventario y Costos
              </h2>
              <p className="text-gray-500 text-sm">
                Cálculo de costo medio ponderado y ajustes.
              </p>
            </div>

            <table className="w-full text-sm">
              <thead>
                <tr className="text-left border-b">
                  <th className="pb-3">Artículo</th>
                  <th className="pb-3">CMP</th>
                  <th className="pb-3">P.V.</th>
                  <th className="pb-3">Stock</th>
                </tr>
              </thead>

              <tbody>
                {productos.map((producto) => (
                  <tr key={producto.id} className="border-b">
                    <td className="py-3">{producto.titulo}</td>
                    <td>Bs {producto.precio * 0.65}</td>
                    <td>Bs {producto.precio}</td>
                    <td>{producto.stock}</td>
                  </tr>
                ))}
              </tbody>
            </table>

            <div className="grid grid-cols-3 gap-3 mt-6">
              <button className="bg-green-100 py-3 rounded-2xl text-sm font-medium">
                Donación
              </button>

              <button className="bg-yellow-100 py-3 rounded-2xl text-sm font-medium">
                Compra GND
              </button>

              <button className="bg-red-100 py-3 rounded-2xl text-sm font-medium">
                Pérdida
              </button>
            </div>
          </div>

          <div className="bg-white rounded-3xl shadow-sm p-6">
            <div className="mb-5 flex items-center justify-between">
              <div>
                <h2 className="text-2xl font-semibold">
                  Contabilidad Automatizada
                </h2>
                <p className="text-gray-500 text-sm">
                  Libro Diario y partida doble.
                </p>
              </div>

              <div className="bg-emerald-100 text-emerald-700 px-4 py-2 rounded-xl text-sm font-semibold">
                Balanceado
              </div>
            </div>

            <div className="space-y-4">
              {asientos.map((asiento, index) => (
                <div
                  key={index}
                  className="border rounded-2xl p-4"
                >
                  <div className="flex items-center justify-between">
                    <h3 className="font-semibold">{asiento.glosa}</h3>
                    <span className="text-sm text-gray-500">
                      {asiento.debe} = {asiento.haber}
                    </span>
                  </div>

                  <div className="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div className="bg-gray-50 rounded-xl p-3">
                      <p className="text-gray-500">Debe</p>
                      <p className="font-bold">{asiento.debe}</p>
                    </div>

                    <div className="bg-gray-50 rounded-xl p-3">
                      <p className="text-gray-500">Haber</p>
                      <p className="font-bold">{asiento.haber}</p>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="bg-white rounded-3xl shadow-sm p-6">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
              <h2 className="text-2xl font-semibold">
                Simulador de Facturación Electrónica
              </h2>
              <p className="text-gray-500 text-sm mt-2">
                Simulación de CUFD/CUIS y recibo fiscal boliviano.
              </p>
            </div>

            <button className="bg-black text-white px-5 py-3 rounded-2xl">
              Generar Recibo
            </button>
          </div>

          <div className="mt-6 border rounded-3xl p-6 bg-gray-50">
            <div className="flex items-center justify-between border-b pb-4">
              <div>
                <h3 className="text-xl font-bold">LIBRERÍA CENTRAL SRL</h3>
                <p className="text-sm text-gray-500">
                  NIT: 1020304050 · Santa Cruz - Bolivia
                </p>
              </div>

              <div className="text-right text-sm">
                <p>Recibo Informativo #0001</p>
                <p>CUFD: SIM-2026-001</p>
              </div>
            </div>

            <div className="mt-5 space-y-2 text-sm">
              <div className="flex justify-between">
                <span>Cien Años de Soledad</span>
                <span>Bs 120</span>
              </div>

              <div className="flex justify-between">
                <span>Agenda Ejecutiva 2026</span>
                <span>Bs 45</span>
              </div>
            </div>

            <div className="mt-5 border-t pt-4 flex justify-between font-bold text-lg">
              <span>Total</span>
              <span>Bs 165</span>
            </div>

            <div className="mt-6 bg-white border rounded-2xl p-4 text-sm text-gray-600">
              “Este documento es una representación gráfica simulada para fines del MVP.”
            </div>
          </div>
        </section>
      </div>
    </div>
  );
}
