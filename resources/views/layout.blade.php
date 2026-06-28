@php
    $branding = app(\App\Services\BrandingSettings::class);
    $cssVariables = $branding->cssVariables();
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        @hasSection('page_title')
            @yield('page_title') -
        @endif
        {{ $branding->appName() }}
    </title>
    <meta name="theme-color" content="{{ $branding->get(\App\Services\BrandingSettings::KEY_PRIMARY_COLOR, '#7e22ce') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            @foreach ($cssVariables as $name => $value)
            {{ $name }}: {{ $value }};
            @endforeach
        }
    </style>
    @vite('resources/css/app.css')
    @stack('styles')
    @stack('vite')
    @vite('resources/js/app.js')

    <script>
        const BASE_URL = @js(rtrim(route('homepage'), '/'));
        const APP_LOCALE = @js($locale);
    </script>
</head>

<body class="min-h-screen bg-gray-50 font-sans text-sm text-gray-700 antialiased">

    @include('header')

    <main class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="fi-section">
            @yield('content')
        </div>
    </main>

    @include('footer')

    @stack('scripts')
</body>
</html>
