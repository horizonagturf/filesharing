@extends('layout')

@section('page_title', __('app.account-title'))

@section('content')
	<div class="p-5">
		<h2 class="font-title text-2xl mb-5 text-primary font-medium uppercase">
			@lang('app.account-title')
		</h2>

		<dl class="space-y-4 text-sm">
			<div>
				<dt class="font-title uppercase text-slate-500 text-xs">@lang('app.account-name')</dt>
				<dd class="text-slate-800">{{ $user->name ?? '—' }}</dd>
			</div>

			<div>
				<dt class="font-title uppercase text-slate-500 text-xs">@lang('app.login')</dt>
				<dd class="text-slate-800">{{ $user->username }}</dd>
			</div>

			<div>
				<dt class="font-title uppercase text-slate-500 text-xs">@lang('app.account-email')</dt>
				<dd class="text-slate-800">{{ $user->email ?? '—' }}</dd>
			</div>

			<div>
				<dt class="font-title uppercase text-slate-500 text-xs">@lang('app.account-roles')</dt>
				<dd class="text-slate-800">{{ $user->roleSlugs()->implode(', ') }}</dd>
			</div>

			<div>
				<dt class="font-title uppercase text-slate-500 text-xs">@lang('app.account-groups')</dt>
				<dd class="text-slate-800">
					@if ($user->groups->isEmpty())
						—
					@else
						{{ $user->groups->pluck('name')->implode(', ') }}
					@endif
				</dd>
			</div>

			<div>
				<dt class="font-title uppercase text-slate-500 text-xs">@lang('app.account-approval-override')</dt>
				<dd class="text-slate-800">
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
				<dt class="font-title uppercase text-slate-500 text-xs">@lang('app.account-approval-effective')</dt>
				<dd class="text-slate-800">
					{{ $requiresApproval ? __('app.yes') : __('app.no') }}
				</dd>
			</div>

			<div>
				<dt class="font-title uppercase text-slate-500 text-xs">@lang('app.account-last-login')</dt>
				<dd class="text-slate-800">
					{{ $user->last_login_at?->format('Y-m-d H:i') ?? '—' }}
				</dd>
			</div>
		</dl>

		<div class="mt-10 text-center">
			<a
				href="{{ route('homepage') }}"
				class="border px-5 py-3 border-primary rounded hover:bg-primary hover:text-white text-primary font-title uppercase text-sm"
			>
				@lang('app.account-view-bundles')
			</a>
		</div>
	</div>
@endsection
