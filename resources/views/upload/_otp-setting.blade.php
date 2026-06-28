@if ($canChooseOtp ?? false)
<div class="mt-5" x-show="isInvitationMode()" x-cloak>
    <label class="flex cursor-pointer items-start gap-3 rounded-lg p-3 ring-1 ring-gray-200 hover:bg-gray-50">
        <input
            type="checkbox"
            name="require_otp"
            class="mt-1 rounded text-primary focus:ring-primary"
            x-model.boolean="bundle.require_otp"
        />
        <span>
            <span class="block text-sm font-medium text-gray-900">@lang('sharing.require-otp')</span>
            <span class="block text-xs text-gray-500">@lang('sharing.require-otp-help')</span>
        </span>
    </label>
    <x-ui.alert variant="info" class="mt-3" x-show="bundle.require_otp" x-cloak>
        @lang('sharing.require-otp-enabled-info')
    </x-ui.alert>
    <x-ui.alert variant="warning" class="mt-3" x-show="! bundle.require_otp" x-cloak>
        @lang('sharing.require-otp-disabled-warning')
    </x-ui.alert>
    <p class="mt-2">
        <x-help-link topic="sharing-and-recipients" />
    </p>
</div>
@endif
