<div x-cloak x-show="step == 2">
    <div x-cloak x-show="bundle.status === 'denied'" class="mb-5">
        <x-ui.alert variant="danger">
            <p class="font-medium">@lang('approval.denied-message')</p>
            <p x-show="bundle.denial_reason" class="mt-1 text-sm">
                @lang('approval.denied-reason'): <span x-text="bundle.denial_reason"></span>
            </p>
            <p class="mt-1 text-sm">@lang('approval.resubmit-hint')</p>
        </x-ui.alert>
    </div>

    <x-ui.page-header :title="__('app.upload-files-title')">
        <x-slot:actions>
            <div class="flex items-center gap-2 text-xs">
                <x-ui.badge variant="primary" x-text="countFilesOnServer()"></x-ui.badge>
                <span class="text-gray-400">/</span>
                <x-ui.badge variant="gray" x-text="maxFiles"></x-ui.badge>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-1">
            <form class="dropzone relative min-h-[160px] rounded-lg" id="upload-frm" enctype="multipart/form-data">
                <div class="absolute bottom-2 right-2 text-[.65rem] italic text-gray-500">
                    @lang('app.maximum-filesize') {{ Upload::fileMaxSize(true) }}
                </div>
            </form>
        </div>

        <div class="lg:col-span-2">
            <p class="text-sm text-gray-400" x-show="Object.keys(bundle.files).length == 0">@lang('app.no-file')</p>

            <ul class="max-h-48 divide-y divide-gray-100 overflow-y-auto rounded-lg ring-1 ring-gray-950/5" x-show="Object.keys(bundle.files).length > 0">
                <template x-for="(f, k) in bundle.files" :key="k">
                    <li
                        title="{{ __('app.click-to-remove') }}"
                        class="relative flex cursor-pointer items-center px-3 py-2 text-sm hover:bg-gray-50"
                        x-on:click="deleteFile(f)"
                    >
                        <div class="absolute inset-y-0 left-0 bg-primary/20 transition-all" :style="'width: ' + (f.progress || 0) + '%;'"></div>

                        <div class="relative flex w-full items-center gap-2">
                            <span class="w-5 shrink-0">
                                <template x-if="f.status === true">
                                    <x-ui.icon name="check-circle" class="h-4 w-4 text-green-600" />
                                </template>
                                <template x-if="f.status === false">
                                    <x-ui.icon name="x-circle" class="h-4 w-4 text-red-600" />
                                </template>
                                <template x-if="f.status === 'uploading'">
                                    <x-ui.icon name="clock" class="h-4 w-4 text-amber-600" />
                                </template>
                            </span>
                            <span class="min-w-0 flex-1 truncate text-gray-900" x-text="f.original"></span>
                            <span class="shrink-0 text-xs text-gray-500" x-text="humanSize(f.filesize)"></span>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    <div class="mt-8 flex justify-between gap-4">
        <x-ui.button variant="secondary" icon="chevron-left" icon-position="left" x-on:click="back()">
            @lang('app.back')
        </x-ui.button>
        <x-ui.button variant="primary" icon="chevron-right" x-on:click="completeStep()">
            @lang('app.complete-upload')
        </x-ui.button>
    </div>
</div>
