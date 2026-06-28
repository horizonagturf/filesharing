@php
    $branding = app(\App\Services\BrandingSettings::class);
    $logoUrl = $branding->logoUrl();
@endphp
<header class="relative bg-gradient-to-r from-primary-light to-primary px-2 py-4 text-center">
	<h1 class="relative font-title font-medium font-body text-4xl text-center text-white uppercase">
		<div class="grow text-center">
			<a href="{{ route('homepage') }}" class="inline-flex items-center justify-center gap-3">
                <img src="{{ $logoUrl }}" alt="{{ $branding->appName() }}" class="h-10 w-auto max-w-[160px] object-contain">
				{{ $branding->appName() }}
			</a>
		</div>
	</h1>

	@include('partials.nav-menu')
</header>
