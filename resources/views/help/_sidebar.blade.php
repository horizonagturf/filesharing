@php
    $help = app(\App\Services\HelpContent::class);
    $current = $currentTopic ?? null;
@endphp

<nav aria-label="@lang('help.all-topics')" class="space-y-1">
    <a
        href="{{ route('help.index') }}"
        class="block rounded-lg px-3 py-2 text-sm font-medium {{ $current === null ? 'bg-gray-100 text-primary' : 'text-gray-700 hover:bg-gray-50' }}"
    >
        @lang('help.page-title')
    </a>

    @foreach ($topics as $slug)
        <a
            href="{{ route('help.show', ['topic' => $slug]) }}"
            class="block rounded-lg px-3 py-2 text-sm {{ $current === $slug ? 'bg-gray-100 font-medium text-primary' : 'text-gray-700 hover:bg-gray-50' }}"
        >
            {{ $help->title($slug) }}
        </a>
    @endforeach
</nav>
