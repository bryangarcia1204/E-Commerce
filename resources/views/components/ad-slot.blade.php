@props(['position' => 'default'])

@php
    // Resuelve el anuncio activo de la posición vía AdsService (cacheado,
    // respetando el master switch ADS_ENABLED). Si no hay anuncio propio en
    // BD pero hay script de proveedor externo configurado (Adsterra,
    // Monetag...), se renderiza como respaldo.
    $ad = app(\App\Services\AdsService::class)->forPosition($position);
@endphp

@if ($ad)
    <div class="ad-slot my-6" data-ad-position="{{ $position }}">
        @if ($ad->type === \App\Models\Ad::TYPE_HTML)
            {{-- Snippet del proveedor: sólo lo puede crear un admin. --}}
            {!! $ad->html !!}
        @else
            <a href="{{ route('ads.click', $ad) }}"
               target="_blank" rel="noopener noreferrer sponsored"
               class="block rounded-xl overflow-hidden bg-gray-100">
                <img src="{{ $ad->image_url ?: asset('img/placeholder.svg') }}"
                     alt="Publicidad" loading="lazy"
                     class="w-full h-auto object-cover">
            </a>
            {{-- Píxel de impresión: funciona igual en HTML cacheado porque
                 la URL es determinística por anuncio. --}}
            <img src="{{ route('ads.impression', $ad) }}" width="1" height="1" alt=""
                 class="hidden" loading="lazy" aria-hidden="true" tabindex="-1">
        @endif
    </div>
@elseif (config('services.ads.enabled', false) && config('services.ads.script'))
    {{-- Respaldo: script del proveedor externo para posiciones sin anuncio
         propio en BD (ADS_SCRIPT en .env). --}}
    <div class="ad-slot my-6" data-ad-position="{{ $position }}">
        {!! config('services.ads.script') !!}
    </div>
@endif
