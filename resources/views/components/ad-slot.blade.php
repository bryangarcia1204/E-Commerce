@props(['position' => 'default'])

{{-- Solo renderiza si la variable de entorno está activa --}}
@if(config('services.ads.enabled', false))
    <div class="ad-slot my-6" data-ad-position="{{ $position }}">
        {{-- Pega aquí el script que te da Adsterra/Monetag para esa posición --}}
        {{-- Ejemplo genérico: --}}
        {{-- <script async src="//pl123456.adsterra.com/..."></script> --}}
    </div>
@endif