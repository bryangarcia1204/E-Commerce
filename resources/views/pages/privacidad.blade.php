@extends('layouts.app')
@section('title', 'Política de privacidad — MODA')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-14">
    <h1 class="text-3xl font-black mb-2">Política de privacidad</h1>
    <p class="text-sm text-gray-500 mb-8">Última actualización: {{ now()->format('d/m/Y') }}</p>

    <div class="prose prose-sm max-w-none text-gray-700 space-y-6">
        <h2 class="text-xl font-bold text-gray-900">1. Responsable del tratamiento</h2>
        <p>MODA trata los datos personales recabados a través de este sitio web. Para cualquier cuestión relacionada con tus datos personales puedes contactar con el Delegado de Protección de Datos (DPO) en <a href="mailto:dpo@moda.local" class="text-brand underline">dpo@moda.local</a>.</p>

        <h2 class="text-xl font-bold text-gray-900">2. Datos que tratamos</h2>
        <ul class="list-disc pl-5 space-y-1">
            <li><strong>Cuenta y perfil:</strong> nombre, email, teléfono (opcional), dirección de envío preferida.</li>
            <li><strong>Autenticación:</strong> hash de contraseña (nunca la contraseña en claro), tokens de sesión y de API.</li>
            <li><strong>Métodos de pago:</strong> NO almacenamos el número de tarjeta ni el CVV. Guardamos sólo los últimos 4 dígitos y un token opaco devuelto por la pasarela de pagos.</li>
            <li><strong>Billetera virtual:</strong> saldo y movimientos (crédito promocional, cashback, reembolsos, consumos).</li>
            <li><strong>Pedidos:</strong> datos de envío y resumen de compra. Los conservamos mientras exista obligación legal contable/fiscal.</li>
            <li><strong>Contacto:</strong> mensajes que nos envías vía el formulario de contacto.</li>
            <li><strong>Afiliación:</strong> registro de clics salientes a tiendas asociadas (IP y sesión) para auditoría de comisiones.</li>
        </ul>

        <h2 class="text-xl font-bold text-gray-900">3. Finalidades y base legal</h2>
        <ul class="list-disc pl-5 space-y-1">
            <li>Ejecución del contrato de compra y gestión del pedido (art. 6.1.b RGPD).</li>
            <li>Gestión de la cuenta de usuario y métodos de pago (art. 6.1.b).</li>
            <li>Cumplimiento de obligaciones legales contables/fiscales (art. 6.1.c).</li>
            <li>Atención al cliente y respuesta a mensajes de contacto (art. 6.1.b / interés legítimo).</li>
            <li>Auditoría de comisiones de afiliación y prevención de fraude (interés legítimo, art. 6.1.f).</li>
            <li>Envío de comunicaciones comerciales sólo con tu consentimiento (art. 6.1.a), revocable en cualquier momento.</li>
        </ul>

        <h2 class="text-xl font-bold text-gray-900">4. Conservación</h2>
        <p>Los datos se conservan mientras exista relación contractual y, después, durante los plazos legales aplicables (fiscal/contable). Los datos de tarjetas tokenizadas se conservan mientras el método de pago esté activo y se borran al eliminar la cuenta. Los pedidos se anonimizan al borrar la cuenta (se conservan totales para auditoría, pero se eliminan los datos identificativos).</p>

        <h2 class="text-xl font-bold text-gray-900">5. Derechos de los interesados</h2>
        <p>Puedes ejercer en cualquier momento tus derechos de acceso, rectificación, supresión (derecho al olvido), limitación, portabilidad y oposición:</p>
        <ul class="list-disc pl-5 space-y-1">
            <li><strong>Supresión de cuenta (derecho al olvido):</strong> desde tu <a href="{{ route('account.profile.edit') }}" class="text-brand underline">perfil</a> puedes borrar tu cuenta. Se eliminan en cascada: métodos de pago, billetera, sesiones, tokens de API, mensajes de contacto. Los pedidos se anonimizan.</li>
            <li><strong>Resto de derechos:</strong> escríbenos a <a href="mailto:dpo@moda.local" class="text-brand underline">dpo@moda.local</a>.</li>
        </ul>
        <p>También puedes reclamar ante la Agencia Española de Protección de Datos (AEPD, <a href="https://www.aepd.es" target="_blank" rel="noopener noreferrer" class="text-brand underline">www.aepd.es</a>) si consideras que tus derechos no se han atendido.</p>

        <h2 class="text-xl font-bold text-gray-900">6. Encargados de tratamiento</h2>
        <p>Compartimos datos, bajo contrato de encargado de tratamiento, con: proveedor de hosting, pasarela de pagos, proveedor de email transaccional y transportistas. Las tiendas asociadas (Shein, AliExpress, Amazon...) actúan como responsables independientes de los datos que les facilitas al comprar en su web.</p>

        <h2 class="text-xl font-bold text-gray-900">7. Medidas de seguridad</h2>
        <ul class="list-disc pl-5 space-y-1">
            <li>Cifrado TLS en todo el sitio.</li>
            <li>Hashing de contraseñas (bcrypt/argon2).</li>
            <li>Tokens de pago opacos; nunca PAN completos ni CVV.</li>
            <li>Rate limiting y validación de propiedad en cada endpoint sensible.</li>
            <li>Auditoría de borrados de cuenta.</li>
        </ul>
    </div>
</div>
@endsection
