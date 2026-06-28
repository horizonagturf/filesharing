<template x-if="step == 3">
    <div x-show="step == 3">
        <template x-if="bundle.status === 'pending_approval'">
            <div>
                <x-ui.page-header :title="__('approval.status-pending_approval')" />
                <x-ui.alert variant="info">@lang('approval.pending-message')</x-ui.alert>
                <template x-if="isInvitationMode() && bundle.recipients && bundle.recipients.length">
                    <p class="mt-2 text-sm text-gray-500">@lang('invitation.recipients-pending')</p>
                </template>
            </div>
        </template>

        <template x-if="isInvitationMode() && (bundle.status === 'sent' || bundle.status === 'approved') && bundle.recipients && bundle.recipients.length">
            <div>
                <x-ui.page-header :title="__('invitation.recipients-sent')" />
                <ul class="divide-y divide-gray-100 rounded-lg ring-1 ring-gray-950/5">
                    <template x-for="recipient in bundle.recipients" :key="recipient.id">
                        <li class="flex items-center justify-between px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-gray-900" x-text="recipient.email"></p>
                                <p class="text-xs text-gray-500">
                                    <span x-show="recipient.verified_at">@lang('invitation.recipient-verified')</span>
                                    <span x-show="! recipient.verified_at && recipient.invited_at">@lang('invitation.recipient-invited')</span>
                                    <span x-show="! recipient.invited_at">@lang('invitation.recipient-pending')</span>
                                </p>
                            </div>
                            <x-ui.button
                                variant="link"
                                size="sm"
                                x-on:click="resendInvitation(recipient)"
                                x-show="recipient.invited_at"
                            >
                                @lang('invitation.resend-invitation')
                            </x-ui.button>
                        </li>
                    </template>
                </ul>
            </div>
        </template>

        <template x-if="! isInvitationMode() && bundle.preview_link">
            <div>
                <x-ui.page-header :title="__('app.download-links')" />

                <div class="space-y-4">
                    <div class="relative">
                        <x-ui.label>@lang('app.preview-link')</x-ui.label>
                        <x-ui.tooltip x-show="copynotify.preview" x-on:click.away="copynotify.preview = false" />
                        <div class="mt-1 flex overflow-hidden rounded-lg ring-1 ring-gray-300 focus-within:ring-2 focus-within:ring-primary">
                            <input id="copy-preview" x-model="bundle.preview_link" class="fi-input !rounded-none !shadow-none !ring-0 flex-1" type="text" readonly x-on:click="selectCopy($el)" />
                            <a class="flex items-center px-3 text-gray-500 hover:text-primary" title="@lang('app.open-in-a-new-tab')" :href="bundle.preview_link" target="_blank" rel="noopener">
                                <x-ui.icon name="arrow-top-right-on-square" class="h-4 w-4" />
                            </a>
                        </div>
                    </div>

                    <div class="relative">
                        <x-ui.label>@lang('app.direct-link')</x-ui.label>
                        <x-ui.tooltip x-show="copynotify.direct_download" x-on:click.away="copynotify.direct_download = false" />
                        <div class="mt-1 flex overflow-hidden rounded-lg ring-1 ring-gray-300 focus-within:ring-2 focus-within:ring-primary">
                            <input id="copy-direct-download" x-model="bundle.download_link" class="fi-input !rounded-none !shadow-none !ring-0 flex-1" type="text" readonly x-on:click="selectCopy($el)" />
                            <a class="flex items-center px-3 text-gray-500 hover:text-primary" title="@lang('app.open-in-a-new-tab')" :href="bundle.download_link" target="_blank" rel="noopener">
                                <x-ui.icon name="arrow-top-right-on-square" class="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <template x-if="! isBundleExpired()">
                        <x-ui.button variant="danger" icon="trash" x-on:click="deleteBundle()">
                            @lang('app.delete-bundle')
                        </x-ui.button>
                    </template>
                    <template x-if="isBundleExpired()">
                        <p class="text-sm text-gray-500">@lang('app.bundle-expired')</p>
                    </template>
                </div>
            </div>
        </template>
    </div>
</template>
