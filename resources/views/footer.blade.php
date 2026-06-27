@php
    $branding = app(\App\Services\BrandingSettings::class);
    $footerText = $branding->get(\App\Services\BrandingSettings::KEY_FOOTER_TEXT);
    $tosUrl = $branding->get(\App\Services\BrandingSettings::KEY_TOS_URL);
    $privacyUrl = $branding->get(\App\Services\BrandingSettings::KEY_PRIVACY_URL);
@endphp
<footer class="relative mt-5 h-6 text-xs">
    @if ($footerText || $tosUrl || $privacyUrl)
        <div class="ml-3 mt-2 text-slate-500">
            @if ($footerText)
                <span>{{ $footerText }}</span>
            @endif
            @if ($tosUrl)
                <a href="{{ $tosUrl }}" class="text-primary hover:underline" target="_blank" rel="noopener">Terms</a>
            @endif
            @if ($privacyUrl)
                <a href="{{ $privacyUrl }}" class="text-primary hover:underline" target="_blank" rel="noopener">Privacy</a>
            @endif
        </div>
    @endif

	@if ($branding->showCreditFooter())
	<div class="absolute right-0 top-0 text-[.6rem] text-slate-100 text-right px-2 py-1 italic bg-primary rounded-tl-lg">
		Made with
		<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-primary-light fill-primary-superlight inline w-4 h-4">
	  	<path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
		</svg>
	 	by
		<a class="text-white" href="https://github.com/axeloz" target="_blank">axeloz</a>
	</div>
	@endif
</footer>
