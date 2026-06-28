@extends('layout')

@section('page_title', $metadata['title'] ?? __('app.preview-bundle'))

@push('scripts')
<script>
    window.__bundle = @js($bundle);
</script>
@endpush

@section('content')
    <div x-data="download">
        <x-ui.page-header :title="__('app.preview-bundle')">
            <x-slot:actions>
                <x-ui.button variant="primary" icon="download" x-on:click="downloadAll()" x-show="! expired">
                    @lang('app.download-all')
                </x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <template x-if="expired">
            <x-ui.alert variant="warning" class="mb-4">@lang('app.warning-bundle-expired')</x-ui.alert>
        </template>

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
                <li class="flex items-center justify-between px-4 py-2 text-sm even:bg-gray-50">
                    <span class="truncate text-gray-900" x-text="f.original"></span>
                    <span class="ml-4 shrink-0 text-gray-500" x-text="humanSize(f.filesize)"></span>
                </li>
            </template>
        </ul>
    </div>
@endsection
