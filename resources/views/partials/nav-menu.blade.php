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

<div class="relative shrink-0" x-data="{ open: false }" x-on:click.outside="open = false">
    @guest
        <a href="{{ route('login') }}" class="fi-btn-secondary !px-3 !py-1.5 !text-xs">
            @lang('app.do-login')
        </a>
    @endguest

    @auth
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            x-on:click="open = !open"
            :aria-expanded="open"
            aria-haspopup="true"
        >
            <span class="max-w-[120px] truncate">{{ auth()->user()->username }}</span>
            <x-ui.icon name="chevron-down" class="h-4 w-4 text-gray-400" />
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition
            class="absolute right-0 top-full z-50 mt-2 min-w-[200px] rounded-lg bg-white py-1 shadow-lg ring-1 ring-gray-950/5"
            role="menu"
        >
            <a
                href="{{ route('homepage') }}"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 {{ request()->routeIs('homepage') ? 'bg-gray-50 font-medium text-primary' : '' }}"
                role="menuitem"
            >
                @lang('app.nav-home')
            </a>

            <a
                href="{{ route('account') }}"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 {{ request()->routeIs('account') ? 'bg-gray-50 font-medium text-primary' : '' }}"
                role="menuitem"
            >
                @lang('app.nav-account')
            </a>

            @if (auth()->user()->hasAnyRole(UserRole::Reviewer, UserRole::Admin))
                <a
                    href="{{ route('approval.index') }}"
                    class="flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 {{ request()->routeIs('approval.*') ? 'bg-gray-50 font-medium text-primary' : '' }}"
                    role="menuitem"
                >
                    <span>@lang('approval.reviewer-nav')</span>
                    @if ($pendingApprovalCount > 0)
                        <span class="fi-badge-primary">{{ $pendingApprovalCount }}</span>
                    @endif
                </a>
            @endif

            @if (auth()->user()->hasRole(UserRole::Admin))
                <a
                    href="{{ url('/admin') }}"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    role="menuitem"
                >
                    @lang('app.nav-admin')
                </a>
            @endif

            <div class="my-1 border-t border-gray-100"></div>

            <a
                href="{{ route('logout') }}"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                role="menuitem"
            >
                @lang('app.logout')
            </a>
        </div>
    @endauth
</div>
