@extends('layout')

@section('page_title', __('app.account-title'))

@section('content')
    <x-ui.page-header :title="__('app.account-title')" />

    <dl class="grid gap-4 sm:grid-cols-2">
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.account-name')</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $user->name ?? '—' }}</dd>
        </div>

        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.login')</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $user->username }}</dd>
        </div>

        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.account-email')</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $user->email ?? '—' }}</dd>
        </div>

        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.account-roles')</dt>
            <dd class="mt-1 flex flex-wrap gap-1">
                @foreach ($user->roleSlugs() as $role)
                    <x-ui.badge variant="primary">{{ $role }}</x-ui.badge>
                @endforeach
            </dd>
        </div>

        <div class="sm:col-span-2">
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.account-groups')</dt>
            <dd class="mt-1 flex flex-wrap gap-1">
                @if ($user->groups->isEmpty())
                    <span class="text-sm text-gray-900">—</span>
                @else
                    @foreach ($user->groups as $group)
                        <x-ui.badge variant="gray">{{ $group->name }}</x-ui.badge>
                    @endforeach
                @endif
            </dd>
        </div>

        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.account-approval-override')</dt>
            <dd class="mt-1 text-sm text-gray-900">
                @if ($user->requires_approval === null)
                    @lang('app.account-approval-inherit')
                @elseif ($user->requires_approval)
                    @lang('app.yes')
                @else
                    @lang('app.no')
                @endif
            </dd>
        </div>

        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.account-approval-effective')</dt>
            <dd class="mt-1">
                <x-ui.badge :variant="$requiresApproval ? 'warning' : 'success'">
                    {{ $requiresApproval ? __('app.yes') : __('app.no') }}
                </x-ui.badge>
            </dd>
        </div>

        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.account-last-login')</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $user->last_login_at?->format('Y-m-d H:i') ?? '—' }}</dd>
        </div>
    </dl>

    <div class="mt-8 flex justify-end">
        <x-ui.button variant="secondary" href="{{ route('homepage') }}" icon="arrow-left" icon-position="left">
            @lang('app.account-view-bundles')
        </x-ui.button>
    </div>
@endsection
