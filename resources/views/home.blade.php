@extends('layouts.app')
@section('title', 'MODA — Moda moderna online')
@section('description', 'Tienda online de moda: ropa, calzado y accesorios. Envío gratis desde 50 € y devoluciones en 30 días.')

@section('content')
{{-- El contenido (hero, novedades, destacados, flash, dropshipping) se
    renderiza y cachea como HTML en HomeController (CacheService). Así no
    se serializan objetos Eloquent en la caché (lo que provocaba
    "incomplete object" al deserializar) y el layout (cabecera, carrito,
    auth) sigue siendo dinámico por usuario. --}}
{!! $content !!}
@endsection
