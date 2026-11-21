@extends('layouts.app')
@section('title', 'Envíos — MODA')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-14">
    <h1 class="text-3xl font-black mb-6">Envíos</h1>

    <div class="prose prose-sm max-w-none text-gray-700 space-y-6">
        <p>Enviamos a toda España peninsular, Islas Baleares, Ceuta y Melilla. Para pedidos internacionales (Unión Europea) consulta disponibilidad en <a href="{{ route('pages.contacto') }}" class="text-brand underline">contacto</a>.</p>

        <h2 class="text-xl font-bold text-gray-900">Plazos de entrega</h2>
        <ul class="list-disc pl-5 space-y-1">
            <li><strong>Productos propios (stock MODA):</strong> 24–48 h laborables tras la confirmación del pago.</li>
            <li><strong>Productos de tiendas asociadas (dropshipping):</strong> 3–10 días laborables, gestionados directamente por la tienda original (Shein, AliExpress, Amazon...). El plazo depende del almacén de origen y del método de envío seleccionado en la tienda.</li>
            <li><strong>Pedidosmixtos:</strong> recibes primero los productos propios; los de tiendas asociadas llegan por separado según el plazo de cada tienda.</li>
        </ul>

        <h2 class="text-xl font-bold text-gray-900">Costes</h2>
        <ul class="list-disc pl-5 space-y-1">
            <li><strong>Envío gratis</strong> en pedidos de productos propios desde 50 €.</li>
            <li>Pedidos por debajo de 50 €: 4,99 € de envío.</li>
            <li>Los productos de tiendas asociadas siguen la política de envíos de la tienda original; el coste (si lo hay) se muestra al finalizar la compra en su web.</li>
        </ul>

        <h2 class="text-xl font-bold text-gray-900">Seguimiento</h2>
        <p>Cada pedido incluye número de seguimiento tan pronto como el transportista o la tienda asociada lo facilitan. Puedes consultarlo desde <a href="{{ route('orders.index') }}" class="text-brand underline">Mis pedidos</a>.</p>

        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-900">
            <strong>Nota sobre dropshipping:</strong> los productos marcados como "tienda asociada" se compran directamente en la tienda original a través de nuestro enlace de afiliado. MODA canaliza la venta, pero el contrato de transporte lo gestiona la tienda externa.
        </div>
    </div>
</div>
@endsection
