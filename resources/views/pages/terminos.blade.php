@extends('layouts.app')
@section('title', 'Términos y condiciones — MODA')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-14">
    <h1 class="text-3xl font-black mb-2">Términos y condiciones</h1>
    <p class="text-sm text-gray-500 mb-8">Última actualización: {{ now()->format('d/m/Y') }}</p>

    <div class="prose prose-sm max-w-none text-gray-700 space-y-6">
        <h2 class="text-xl font-bold text-gray-900">1. Objeto</h2>
        <p>Estos términos regulan el uso del sitio MODA y las compras realizadas a través del mismo. La utilización del sitio implica la aceptación expresa de estos términos.</p>

        <h2 class="text-xl font-bold text-gray-900">2. Modelo de venta</h2>
        <p>MODA opera con dos modelos de producto:</p>
        <ul class="list-disc pl-5 space-y-1">
            <li><strong>Productos propios:</strong> stock gestionado por MODA. El contrato de compraventa se formaliza entre el usuario y MODA, que asume el envío y la garantía.</li>
            <li><strong>Productos de tiendas asociadas (dropshipping + afiliación):</strong> MODA actúa como promotor/afiliado. El usuario navega y selecciona el producto en MODA, pero <strong>la compra se formaliza directamente con la tienda original</strong> (Shein, AliExpress, Amazon...) a través del enlace de afiliado. La tienda externa gestiona el pago, el envío, la garantía y las devoluciones según sus propias condiciones. MODA recibe una comisión o un margen por canalizar la venta; el precio mostrado en MODA puede incluir un sobreprecio sobre el precio de la tienda original.</li>
        </ul>

        <h2 class="text-xl font-bold text-gray-900">3. Precios</h2>
        <p>Los precios mostrados incluyen IVA. En productos de tiendas asociadas, el precio final puede variar si la tienda externa lo actualiza entre el momento de la visualización en MODA y la compra en su web; prevalece el precio mostrado por la tienda en el momento del pago.</p>

        <h2 class="text-xl font-bold text-gray-900">4. Billetera virtual</h2>
        <p>El saldo de la billetera MODA es un crédito interno utilizable en productos propios. No es un medio de pago regulado ni dinero electrónico; no es reembolsable en efectivo salvo obligación legal. Los movimientos son inmutables y auditables.</p>

        <h2 class="text-xl font-bold text-gray-900">5. Cuenta de usuario</h2>
        <p>El usuario se compromete a facilitar datos veraces y a mantener la confidencialidad de su contraseña. El uso fraudulento, el abuso del rate-limiting o la manipulación de comisiones de afiliación mediante clics automatizados faculta a MODA para suspender o eliminar la cuenta sin perjuicio de las acciones legales pertinentes.</p>

        <h2 class="text-xl font-bold text-gray-900">6. Propiedad intelectual</h2>
        <p>El contenido del sitio (marcas, textos, imágenes propias) pertenece a MODA. Las imágenes y datos de productos de tiendas asociadas pertenecen a sus respectivos titulares y se muestran bajo acuerdo de afiliación.</p>

        <h2 class="text-xl font-bold text-gray-900">7. Legislación aplicable</h2>
        <p>Estos términos se rigen por la legislación española. Para la resolución de conflictos, las partes se someten a los juzgados del domicilio del consumidor cuando éste tenga la condición de consumidor.</p>
    </div>
</div>
@endsection
