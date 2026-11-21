@extends('layouts.app')

@section('title', $title)

@section('content')
{{-- El contenido (filtros, grid, paginación) se renderiza y cachea como HTML
    en ProductController (CacheService), codificado por q + categoría + orden
    + página. No se cachean objetos Eloquent (evita "incomplete object").
    El layout (cabecera, carrito, auth) sigue dinámico. --}}
{!! $content !!}
@endsection
