@php
    use App\Enums\ApprovalRequestStatus;
    use App\Enums\UserRole;
    use App\Models\ApprovalRequest;

    $pendingApprovalCount = 0;
    if (auth()->check() && auth()->user()->hasAnyRole(UserRole::Reviewer, UserRole::Admin)) {
        $pendingApprovalCount = ApprovalRequest::query()
            ->where('status', ApprovalRequestStatus::Pending)
            ->count();
    }
@endphp

<div class="absolute right-3 top-1/2 -translate-y-1/2 text-left" x-data="{ open: false }" x-on:click.outside="open = false">
	@guest
		<a href="{{ route('login') }}" class="text-xs text-white/90 hover:text-white underline uppercase">
			@lang('app.do-login')
		</a>
	@endguest

	@auth
		<button
			type="button"
			class="flex items-center gap-1 text-xs text-white/90 hover:text-white uppercase"
			x-on:click="open = !open"
			aria-expanded="false"
			aria-haspopup="true"
		>
			<span>{{ auth()->user()->username }}</span>
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
				<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
			</svg>
		</button>

		<div
			x-show="open"
			x-cloak
			class="absolute right-0 top-full mt-2 min-w-[180px] rounded-md bg-white py-1 shadow-lg border border-primary-superlight z-50 text-left normal-case"
		>
			<a
				href="{{ route('homepage') }}"
				class="block px-4 py-2 text-sm text-slate-700 hover:bg-purple-50 {{ request()->routeIs('homepage') ? 'bg-purple-50 text-primary font-medium' : '' }}"
			>
				@lang('app.nav-home')
			</a>

			<a
				href="{{ route('account') }}"
				class="block px-4 py-2 text-sm text-slate-700 hover:bg-purple-50 {{ request()->routeIs('account') ? 'bg-purple-50 text-primary font-medium' : '' }}"
			>
				@lang('app.nav-account')
			</a>

			@if (auth()->user()->hasAnyRole(UserRole::Reviewer, UserRole::Admin))
				<a
					href="{{ route('approval.index') }}"
					class="flex items-center justify-between gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-purple-50 {{ request()->routeIs('approval.*') ? 'bg-purple-50 text-primary font-medium' : '' }}"
				>
					<span>@lang('approval.reviewer-nav')</span>
					@if ($pendingApprovalCount > 0)
						<span class="text-xs bg-primary text-white rounded-full px-2 py-0.5">{{ $pendingApprovalCount }}</span>
					@endif
				</a>
			@endif

			@if (auth()->user()->hasRole(UserRole::Admin))
				<a
					href="{{ url('/admin') }}"
					class="block px-4 py-2 text-sm text-slate-700 hover:bg-purple-50"
				>
					@lang('app.nav-admin')
				</a>
			@endif

			<div class="border-t border-gray-100 my-1"></div>

			<a
				href="{{ route('logout') }}"
				class="block px-4 py-2 text-sm text-slate-700 hover:bg-purple-50"
			>
				@lang('app.logout')
			</a>
		</div>
	@endauth
</div>
