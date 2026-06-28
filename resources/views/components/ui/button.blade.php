@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconPosition' => 'right',
    'loading' => false,
])

@php
    $classes = match ($variant) {
        'primary' => 'fi-btn-primary',
        'secondary' => 'fi-btn-secondary',
        'danger' => 'fi-btn-danger',
        'ghost' => 'fi-btn-ghost',
        'link' => 'fi-btn-link',
        'success' => 'inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-green-700 shadow-sm ring-1 ring-green-300 transition hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60',
        default => 'fi-btn-primary',
    };

    $sizeClasses = match ($size) {
        'sm' => '!px-3 !py-1.5 !text-xs',
        'lg' => '!px-5 !py-2.5 !text-base',
        default => '',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => trim("$classes $sizeClasses")]) }}>
        @if ($icon && $iconPosition === 'left')
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        @endif
        {{ $slot }}
        @if ($icon && $iconPosition === 'right')
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => trim("$classes $sizeClasses")]) }} @if($loading) disabled @endif>
        @if ($loading)
            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        @if ($icon && $iconPosition === 'left' && ! $loading)
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        @endif
        {{ $slot }}
        @if ($icon && $iconPosition === 'right' && ! $loading)
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        @endif
    </button>
@endif
