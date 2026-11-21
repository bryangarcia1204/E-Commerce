@extends('layouts.app')
@section('title', $title)

@section('content')
{{-- El contenido (galería, info, formulario add-to-cart, relacionados) se
    renderiza y cachea como HTML en ProductController (CacheService), con un
    placeholder __CSRF_TOKEN__ que el controlador sustituye por el token real
    de la sesión en cada request. No se cachean objetos Eloquent (evita
    "incomplete object") ni tokens CSRF congelados (evita 419). --}}
{!! $content !!}
@endsection
