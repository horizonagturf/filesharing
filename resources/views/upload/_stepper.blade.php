<div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <template x-for="(s, i) in steps" :key="i">
        <div class="flex items-start gap-3">
            <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                :class="(i + 1) <= step ? 'bg-primary text-white' : 'bg-gray-200 text-gray-500'"
                x-text="i + 1"
            ></div>
            <div class="min-w-0 pt-0.5">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500" x-text="'{{ __('app.step') }} ' + (i + 1)"></p>
                <p class="text-sm font-semibold text-gray-900" x-text="s.title"></p>
            </div>
        </div>
    </template>
</div>
