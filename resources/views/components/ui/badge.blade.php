@props([
    'variant' => 'gray',
])

@php
    $classes = match ($variant) {
        'primary' => 'fi-badge-primary',
        'success' => 'fi-badge-success',
        'warning' => 'fi-badge-warning',
        'danger' => 'fi-badge-danger',
        default => 'fi-badge-gray',
    };
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
