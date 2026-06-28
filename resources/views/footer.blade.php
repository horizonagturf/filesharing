@php
    $branding = app(\App\Services\BrandingSettings::class);
    $footerText = $branding->get(\App\Services\BrandingSettings::KEY_FOOTER_TEXT);
    $tosUrl = $branding->get(\App\Services\BrandingSettings::KEY_TOS_URL);
    $privacyUrl = $branding->get(\App\Services\BrandingSettings::KEY_PRIVACY_URL);
@endphp
<footer class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-2 text-xs text-gray-500 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
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

        @if ($branding->showCreditFooter())
            <p class="text-gray-400 italic">
                Made with
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="inline h-3.5 w-3.5 text-primary">
                    <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z" />
                </svg>
                by
                <a class="text-gray-600 hover:text-primary" href="https://github.com/axeloz" target="_blank" rel="noopener">axeloz</a>
            </p>
        @endif
    </div>
</footer>
