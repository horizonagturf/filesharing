@extends('layout')

@section('page_title', __('app.bundle-unavailable-title'))

@section('content')
    <x-ui.empty-state
        icon="folder-open"
        :title="__('app.bundle-unavailable-title')"
    >
        <p class="text-6xl font-bold text-primary">410</p>
        <p class="mt-4 text-sm text-gray-600">
            @if ($reason === 'max_downloads')
                @lang('app.bundle-unavailable-max-downloads')
            @else
                @lang('app.bundle-unavailable-expired')
            @endif
        </p>
        <p class="mt-2 text-sm text-gray-500">@lang('app.bundle-unavailable-contact')</p>
        <x-ui.button variant="secondary" href="{{ route('homepage') }}" class="mt-4" icon="arrow-left" icon-position="left">
            @lang('app.nav-home')
        </x-ui.button>
    </x-ui.empty-state>
@endsection
