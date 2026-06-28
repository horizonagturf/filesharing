@props([
    'show' => 'false',
    'title' => null,
    'confirmText' => null,
    'cancelText' => null,
])

@php
    $confirmText = $confirmText ?? __('app.confirm');
    $cancelText = $cancelText ?? __('app.cancel');
@endphp

<template x-if="{{ $show }}">
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        @if($title) aria-labelledby="modal-title" @endif
    >
        <div class="fixed inset-0 bg-gray-500/75" x-on:click="{{ $attributes->get('on-close', 'false') }}"></div>

        <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-950/5">
            @if ($title)
                <h3 id="modal-title" class="text-base font-semibold text-gray-900">{{ $title }}</h3>
            @endif

            <div class="mt-2 text-sm text-gray-600">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="mt-6 flex justify-end gap-3">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</template>
