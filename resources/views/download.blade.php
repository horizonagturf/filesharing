@extends('layout')

@section('page_title', $bundle['title'] ?? __('app.preview-bundle'))

@push('scripts')
<script>
    window.__bundle = @js($bundle);
    window.__bundlePasswordIncorrect = @js(__('app.bundle-password-incorrect'));
</script>
@endpush

@section('content')
    <div x-data="download">
        <x-ui.page-header :title="__('app.preview-bundle')">
            <x-slot:actions>
                <x-ui.button
                    variant="primary"
                    icon="download"
                    x-show="downloadsUnlocked"
                    x-on:click="downloadAll()"
                >
                    @lang('app.download-all')
                </x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <div
            class="mb-6 rounded-lg bg-amber-50 px-4 py-3 ring-1 ring-amber-200"
            x-show="metadata.password_required && ! metadata.password_unlocked"
            x-cloak
        >
            <p class="mb-3 text-sm text-amber-900">@lang('app.bundle-password-required')</p>
            <form class="flex flex-col gap-3 sm:flex-row sm:items-end" x-on:submit.prevent="unlock()">
                <div class="min-w-0 flex-1">
                    <label for="bundle-password" class="mb-1 block text-xs font-medium uppercase tracking-wide text-amber-800">
                        @lang('app.bundle-password')
                    </label>
                    <input
                        id="bundle-password"
                        type="password"
                        class="fi-input w-full"
                        autocomplete="current-password"
                        x-model="password"
                        :disabled="unlocking"
                    />
                </div>
                <x-ui.button type="submit" variant="primary" x-bind:disabled="unlocking || ! password">
                    @lang('app.unlock-downloads')
                </x-ui.button>
            </form>
            <p class="mt-2 text-sm text-danger-600" x-show="unlockError" x-text="unlockError"></p>
        </div>

        <dl class="mb-6 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.upload-title')</dt>
                <dd class="mt-1 text-sm text-gray-900" x-text="metadata.title"></dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.created-at')</dt>
                <dd class="mt-1 text-sm text-gray-900" x-text="created_at"></dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.upload-expiry')</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    <template x-if="expires_at"><span x-text="expires_at"></span></template>
                    <template x-if="! expires_at"><span>@lang('app.forever')</span></template>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.files')</dt>
                <dd class="mt-1 text-sm text-gray-900" x-text="Object.keys(metadata.files).length"></dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.fullsize')</dt>
                <dd class="mt-1 text-sm text-gray-900" x-text="humanSize(metadata.fullsize)"></dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.current-downloads')</dt>
                <dd class="mt-1 text-sm text-gray-900" x-text="metadata.downloads"></dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.max-downloads')</dt>
                <dd class="mt-1 text-sm text-gray-900" x-text="metadata.max_downloads > 0 ? metadata.max_downloads : '∞'"></dd>
            </div>
            <div class="sm:col-span-2" x-show="metadata.description">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">@lang('app.upload-description')</dt>
                <dd class="prose prose-sm mt-1 max-w-none text-gray-700" x-html="metadata.description_html"></dd>
            </div>
        </dl>

        <h3 class="mb-2 text-sm font-semibold text-gray-900">@lang('app.files-list')</h3>

        <ul class="max-h-48 divide-y divide-gray-100 overflow-y-auto rounded-lg ring-1 ring-gray-950/5" x-show="Object.keys(metadata.files).length > 0">
            <template x-for="f in metadata.files" :key="f.uuid">
                <li class="flex items-center justify-between gap-3 px-4 py-2 text-sm even:bg-gray-50">
                    <div class="flex min-w-0 items-center gap-3">
                        <template x-if="f.thumbnail_url">
                            <img
                                :src="f.thumbnail_url"
                                :alt="f.original"
                                class="h-10 w-10 shrink-0 rounded object-cover ring-1 ring-gray-200"
                            />
                        </template>
                        <span class="truncate text-gray-900" x-text="f.original"></span>
                    </div>
                    <div class="ml-4 flex shrink-0 items-center gap-3">
                        <span class="text-gray-500" x-text="humanSize(f.filesize)"></span>
                        <button
                            type="button"
                            class="font-medium text-primary hover:underline"
                            x-show="downloadsUnlocked && f.download_url"
                            x-on:click="downloadFile(f)"
                        >
                            @lang('app.download')
                        </button>
                    </div>
                </li>
            </template>
        </ul>
    </div>
@endsection
