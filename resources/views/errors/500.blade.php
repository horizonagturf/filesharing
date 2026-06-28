@extends('layout')

@section('content')
    <x-ui.empty-state
        icon="exclamation-triangle"
        :title="__('app.unexpected-error')"
    >
        <p class="text-6xl font-bold text-primary">500</p>
        <x-ui.button variant="secondary" href="{{ route('homepage') }}" class="mt-4" icon="arrow-left" icon-position="left">
            @lang('app.nav-home')
        </x-ui.button>
    </x-ui.empty-state>
@endsection
