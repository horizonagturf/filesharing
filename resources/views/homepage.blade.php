@extends('layout')

@push('scripts')
<script>
    window.__bundles = @js($bundles);
    window.__createdAtLabel = @js(__('app.created-at'));
    window.__pendingLabel = @js(__('app.pending'));
    window.__pendingApprovalLabel = @js(__('approval.status-pending_approval'));
    window.__deniedLabel = @js(__('approval.status-denied'));
    window.__activeLabel = @js(__('app.active'));
    window.__expiredLabel = @js(__('app.expired'));
</script>
@endpush

@section('content')
    <div x-data="bundle">
        <x-ui.page-header :title="__('app.existing-bundles')">
            <x-slot:actions>
                <x-ui.badge variant="primary" x-show="hasBundles()" x-text="bundles.length"></x-ui.badge>
                <x-ui.button
                    variant="primary"
                    icon="plus"
                    x-on:click="newBundle()"
                    ::disabled="loading"
                >
                    @lang('app.create-new-upload')
                </x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @auth
            <template x-if="! hasBundles()">
                <x-ui.empty-state
                    icon="folder-open"
                    :title="__('app.no-existing-bundle')"
                    :description="__('app.or-create')"
                >
                    <x-ui.button variant="primary" icon="plus" x-on:click="newBundle()" ::disabled="loading">
                        @lang('app.create-new-upload')
                    </x-ui.button>
                </x-ui.empty-state>
            </template>

            <template x-if="hasBundles()">
                <div class="space-y-6">
                    <template x-for="group in allGrouped()" :key="group.key">
                        <div>
                            <h3 class="mb-2 text-sm font-semibold text-gray-700" x-text="group.label"></h3>
                            <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.upload-title')</th>
                                            <th scope="col" class="hidden px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500 sm:table-cell">@lang('app.created-at')</th>
                                            <th scope="col" class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        <template x-for="bundle in group.items" :key="bundle.slug">
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3">
                                                    <p class="text-sm font-medium text-gray-900" x-text="bundle.title || 'untitled'"></p>
                                                    <p class="text-xs text-gray-500 sm:hidden" x-text="dayjs(bundle.created_at).fromNow()"></p>
                                                </td>
                                                <td class="hidden px-4 py-3 text-sm text-gray-500 sm:table-cell" x-text="dayjs(bundle.created_at).fromNow()"></td>
                                                <td class="px-4 py-3 text-right">
                                                    <x-ui.button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="chevron-right"
                                                        x-on:click="openBundle(bundle.slug)"
                                                    >
                                                        @lang('app.open')
                                                    </x-ui.button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        @else
            <x-ui.empty-state
                icon="folder-open"
                :title="__('app.no-existing-bundle')"
            >
                <p class="text-sm text-gray-500">
                    <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">@lang('app.do-login')</a>
                    @lang('app.to-get-bundles')
                </p>
            </x-ui.empty-state>
        @endauth
    </div>
@endsection
