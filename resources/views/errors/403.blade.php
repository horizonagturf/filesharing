@extends('layout')

@section('content')
    <x-ui.empty-state
        icon="shield-exclamation"
        :title="__('app.permission-denied')"
    >
        <p class="text-6xl font-bold text-primary">403</p>
        <x-ui.button variant="secondary" href="{{ route('homepage') }}" class="mt-4" icon="arrow-left" icon-position="left">
            @lang('app.nav-home')
        </x-ui.button>
    </x-ui.empty-state>
@endsection
