@php
    $colors = [
        'pending'   => 'bg-yellow-100 text-yellow-800',
        'paid'      => 'bg-blue-100 text-blue-800',
        'shipped'   => 'bg-indigo-100 text-indigo-800',
        'delivered' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
    ];
    $cls = $colors[$status] ?? 'bg-gray-100 text-gray-700';
@endphp
<span class="text-xs {{ $cls }} px-2 py-1 rounded font-medium">{{ ucfirst($status) }}</span>
