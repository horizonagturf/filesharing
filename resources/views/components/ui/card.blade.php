@props([
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-lg bg-gray-50 p-4 ring-1 ring-gray-950/5']) }}>
    @if ($title || isset($header))
        <div class="mb-4 flex items-center justify-between gap-4 border-b border-gray-100 pb-4">
            @if ($title)
                <h2 class="text-base font-semibold text-gray-900">{{ $title }}</h2>
            @else
                <div class="text-base font-semibold text-gray-900">{{ $header }}</div>
            @endif
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
