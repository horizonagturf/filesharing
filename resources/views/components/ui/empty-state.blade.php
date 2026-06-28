@props([
    'icon' => 'folder-open',
    'title' => '',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-12 text-center']) }}>
    <div class="mb-4 rounded-full bg-gray-100 p-3">
        <x-ui.icon :name="$icon" class="h-8 w-8 text-gray-400" />
    </div>

    @if ($title)
        <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
    @endif

    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-4">
            {{ $slot }}
        </div>
    @endif
</div>
