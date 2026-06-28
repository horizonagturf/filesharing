@extends('layout')

@section('content')
    <x-ui.empty-state
        icon="folder-open"
        :title="__('app.page-not-found')"
    >
        <p class="text-6xl font-bold text-primary">404</p>
        <x-ui.button variant="secondary" href="{{ route('homepage') }}" class="mt-4" icon="arrow-left" icon-position="left">
            @lang('app.nav-home')
        </x-ui.button>
    </x-ui.empty-state>
@endsection
