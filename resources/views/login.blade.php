@extends('layout')

@section('page_title', __('app.authentication'))

@php($ssoEnabled = config('sso.enabled'))

@section('content')
    <div @if (! $ssoEnabled) x-data="login" @endif>
        <x-ui.page-header :title="__('app.authentication')" />

        @if (session('status'))
            <x-ui.alert variant="info" class="mb-4">{{ session('status') }}</x-ui.alert>
        @endif

        @if (session('sso_error'))
            <x-ui.alert variant="danger" class="mb-4">{{ session('sso_error') }}</x-ui.alert>
        @endif

        @if ($ssoEnabled)
            <p class="mb-6 text-gray-600">@lang('sso.sso-login-intro')</p>
            @include('partials.microsoft-sign-in-button')
        @else
            <template x-if="error">
                <x-ui.alert variant="danger" class="mb-4" x-text="error"></x-ui.alert>
            </template>

            <div class="space-y-4 max-w-md">
                <x-ui.input
                    id="user-login"
                    type="text"
                    name="login"
                    maxlength="40"
                    :label="__('app.login')"
                    required
                    x-model="user.login"
                    x-on:keydown.enter="loginUser()"
                />

                <x-ui.input
                    id="user-password"
                    type="password"
                    name="password"
                    :label="__('app.password')"
                    required
                    x-model="user.password"
                    x-on:keydown.enter="loginUser()"
                />

                <div class="flex justify-end pt-2">
                    <x-ui.button
                        variant="primary"
                        icon="chevron-right"
                        x-on:click="loginUser()"
                        ::disabled="loading"
                    >
                        @lang('app.do-login')
                    </x-ui.button>
                </div>
            </div>
        @endif
    </div>
@endsection
