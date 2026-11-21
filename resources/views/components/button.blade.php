@props(['variant' => 'primary', 'href' => null, 'type' => 'button'])

@php
$classes = match($variant) {
    'primary'   => 'bg-ink text-white hover:bg-gray-800',
    'secondary' => 'border border-ink text-ink hover:bg-ink hover:text-white',
    'brand'     => 'bg-brand text-white hover:bg-brand-dark',
    'ghost'     => 'text-ink hover:bg-gray-100',
};
$base = 'inline-flex items-center justify-center gap-2 px-6 py-3 rounded-full text-sm font-semibold transition disabled:opacity-50';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => "$base $classes"]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => "$base $classes"]) }}>{{ $slot }}</button>
@endif