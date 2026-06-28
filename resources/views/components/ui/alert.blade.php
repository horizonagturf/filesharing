@props([
    'variant' => 'info',
    'title' => null,
])

@php
    $classes = match ($variant) {
        'success' => 'bg-green-50 text-green-800 ring-green-600/20',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'danger' => 'bg-red-50 text-red-800 ring-red-600/20',
        default => 'bg-blue-50 text-blue-800 ring-blue-600/20',
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg p-4 text-sm ring-1 ring-inset $classes", 'role' => 'alert']) }}>
    @if ($title)
        <p class="mb-1 font-semibold">{{ $title }}</p>
    @endif
    {{ $slot }}
</div>
