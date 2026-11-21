@extends('layouts.app')
@section('title', 'Novedades y tendencias — MODA')

@section('content')
{{-- El listado de noticias + paginación se renderiza y cachea como HTML en
    PageController (CacheService). No hay formularios CSRF aquí (sólo enlaces),
    así que no hace falta sustitución de token. --}}
{!! $content !!}
@endsection
