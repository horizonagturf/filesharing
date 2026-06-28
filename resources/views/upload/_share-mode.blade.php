@if ($canUseStaticLink)
<div class="mt-5">
    <x-ui.label>@lang('sharing.share-mode')</x-ui.label>
    <div class="mt-2 space-y-3">
        <label class="flex cursor-pointer items-start gap-3 rounded-lg p-3 ring-1 ring-gray-200 hover:bg-gray-50">
            <input
                type="radio"
                name="share_mode"
                value="invitation"
                class="mt-1 text-primary focus:ring-primary"
                :checked="bundle.share_mode === 'invitation'"
                x-on:change="setShareMode('invitation')"
            />
            <span>
                <span class="block text-sm font-medium text-gray-900">@lang('sharing.share-mode-invitation')</span>
                <span class="block text-xs text-gray-500">@lang('sharing.share-mode-invitation-help')</span>
            </span>
        </label>
        <label class="flex cursor-pointer items-start gap-3 rounded-lg p-3 ring-1 ring-gray-200 hover:bg-gray-50">
            <input
                type="radio"
                name="share_mode"
                value="static_link"
                class="mt-1 text-primary focus:ring-primary"
                :checked="bundle.share_mode === 'static_link'"
                x-on:change="setShareMode('static_link')"
            />
            <span>
                <span class="block text-sm font-medium text-gray-900">@lang('sharing.share-mode-static-link')</span>
                <span class="block text-xs text-gray-500">@lang('sharing.share-mode-static-link-help')</span>
            </span>
        </label>
    </div>
    <x-ui.alert variant="warning" class="mt-3" x-show="bundle.share_mode === 'static_link'" x-cloak>
        @lang('sharing.static-link-warning')
    </x-ui.alert>
    <p class="mt-2">
        <x-help-link topic="sharing-and-recipients" />
    </p>
</div>
@endif
