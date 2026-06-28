<div {{ $attributes->merge(['class' => 'absolute -top-8 left-1/2 z-50 -translate-x-1/2 rounded-md bg-primary px-2 py-1 text-xs text-white shadow-sm']) }}
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 scale-90"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-90"
    x-cloak
    role="status"
>
    @lang('app.copied')
</div>
