@extends('layouts.app')
@section('title', 'Devoluciones — MODA')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-14">
    <h1 class="text-3xl font-black mb-6">Devoluciones</h1>

    <div class="prose prose-sm max-w-none text-gray-700 space-y-6">
        <p>Tienes <strong>30 días naturales</strong> desde la recepción del pedido para ejercer tu derecho de desistimiento, conforme al Real Decreto Legislativo 1/2007 (Ley General para la Defensa de los Consumidores y Usuarios).</p>

        <h2 class="text-xl font-bold text-gray-900">Productos propios (stock MODA)</h2>
        <ol class="list-decimal pl-5 space-y-1">
            <li>Solicita la devolución desde <a href="{{ route('pages.contacto') }}" class="text-brand underline">contacto</a> indicando el número de pedido y el motivo.</li>
            <li>Recibirás por email la dirección de devolución y una etiqueta prepagada (el coste se deduce del reembolso: 4,99 €).</li>
            <li>Devuelve el producto en su embalaje original, sin usar y con las etiquetas puestas.</li>
            <li>Procesamos el reembolso en el método de pago original en un máximo de 14 días laborables tras recibir la mercancía.</li>
        </ol>

        <h2 class="text-xl font-bold text-gray-900">Productos de tiendas asociadas (dropshipping)</h2>
        <p>Las devoluciones de productos comprados en tiendas asociadas (Shein, AliExpress, Amazon...) se rigen por la <strong>política de devoluciones de la tienda original</strong>, ya que el contrato de compra se formaliza con ella. MODA te asiste en la gestión, pero la decisión final corresponde a la tienda.</p>
        <ul class="list-disc pl-5 space-y-1">
            <li>Consulta la política de la tienda en el momento de la compra (enlace "Devoluciones" de la ficha del producto en su web).</li>
            <li>La mayoría de tiendas asociadas acepta devoluciones en 14–30 días; algunas categorías (ropa interior, productos personalizados) no son retornables.</li>
            <li>Si la devolución es por defecto o error de la tienda, el reembolso corre por cuenta de la tienda externa.</li>
        </ul>

        <h2 class="text-xl font-bold text-gray-900">Reembolso a la billetera</h2>
        <p>Si lo prefieres, puedes solicitar que el reembolso se abone como crédito en tu <strong>billetera MODA</strong> (disponible para futuras compras) en lugar del método de pago original — suele ser inmediato.</p>

        <h2 class="text-xl font-bold text-gray-900">Artículos no retornables</h2>
        <ul class="list-disc pl-5 space-y-1">
            <li>Productos de higiene/cosmética abiertos.</li>
            <li>Ropa interior y bañadores sin embalaje.</li>
            <li>Productos personalizados o a medida.</li>
        </ul>
    </div>
</div>
@endsection
