@php
    $branding = app(\App\Services\BrandingSettings::class);
    $logoUrl = $branding->logoUrl();
@endphp
<header class="sticky top-0 z-30 border-b border-gray-200 bg-white shadow-sm">
    <div class="mx-auto flex h-14 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('homepage') }}" class="flex min-w-0 items-center gap-3 text-gray-900">
            <img src="{{ $logoUrl }}" alt="{{ $branding->appName() }}" class="h-8 w-auto max-w-[140px] shrink-0 object-contain">
            <span class="truncate text-base font-semibold">{{ $branding->appName() }}</span>
        </a>

        @include('partials.nav-menu')
    </div>
</header>
