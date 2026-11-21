@extends('layouts.app')
@section('title', 'Sobre nosotros — MODA')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-14">
    <h1 class="text-3xl font-black mb-6">Sobre MODA</h1>

    <div class="prose prose-sm max-w-none text-gray-700 space-y-6">
        <p>MODA es una tienda online de moda que combina dos modelos de abastecimiento para ofrecer la selección más amplia al mejor precio: un catálogo propio curado y una red de productos de tiendas asociadas (Shein, AliExpress, Amazon, Zara...) accesibles vía nuestro enlace de afiliado.</p>

        <h2 class="text-xl font-bold text-gray-900">Nuestro modelo</h2>
        <p>Operamos como <strong>promotor de ventas</strong> (dropshipping + afiliación): tú descubres y seleccionas productos en MODA; cuando se trata de una tienda asociada, la compra se completa directamente en su web, y ellos gestionan el pago y el envío. MODA cobra un margen o una comisión por canalizar la venta. Así puedes comprar en muchas tiendas desde un solo catálogo, y nosotros cuidamos de mostrarte lo mejor de cada una.</p>

        <h2 class="text-xl font-bold text-gray-900">Novedades y tendencias</h2>
        <p>Mantenemos una sección de <a href="{{ route('pages.novedades') }}" class="text-brand underline">novedades y tendencias</a> actualizada desde medios de moda y promociones de las propias tiendas, para que estés siempre al día de lo que se lleva.</p>

        <h2 class="text-xl font-bold text-gray-900">Privacidad ante todo</h2>
        <p>Tratamos tus datos con el máximo rigor: nunca guardamos los números completos de tus tarjetas, tu saldo de billetera es auditado movimiento a movimiento, y puedes borrar tu cuenta y todos tus datos asociados en cualquier momento desde tu perfil (derecho al olvido). Más detalle en nuestra <a href="{{ route('pages.privacidad') }}" class="text-brand underline">política de privacidad</a>.</p>

        <h2 class="text-xl font-bold text-gray-900">Contacto</h2>
        <p>¿Dudas, sugerencias o propuestas de colaboración? Escríbenos desde el <a href="{{ route('pages.contacto') }}" class="text-brand underline">formulario de contacto</a>.</p>
    </div>
</div>
@endsection
