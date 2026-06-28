<template x-if="modal.show">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-500/75" x-on:click="modal.show = false"></div>
        <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-950/5">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
                <x-ui.icon name="exclamation-triangle" class="h-6 w-6 text-amber-600" />
            </div>
            <h3 class="text-center text-base font-semibold text-gray-900">@lang('app.confirmation')</h3>
            <p class="mt-2 text-center text-sm text-gray-600" x-text="modal.text"></p>
            <div class="mt-6 flex justify-center gap-3">
                <x-ui.button variant="secondary" size="sm" x-on:click="modal.show = false">@lang('app.cancel')</x-ui.button>
                <x-ui.button variant="primary" size="sm" x-on:click="confirmModal()">@lang('app.confirm')</x-ui.button>
            </div>
        </div>
    </div>
</template>
